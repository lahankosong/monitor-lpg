<?php
namespace App\Http\Controllers\Agen;

use App\Http\Controllers\Controller;
use App\Models\{
    SuratJalanHeader, SuratJalanDetail,
    SjSisaDistribusi, SjPengalihan, SjDetailTambahan,
    StokArmada, GudangStok,
    TransaksiAntarAgen, Pangkalan, Agen
};
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DistribusiController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // INDEX — Halaman realisasi distribusi
    // ─────────────────────────────────────────────────────────────────────────
    public function index(Request $request)
    {
        $tanggal = $request->get('tanggal', now()->toDateString());
        $bulan   = $request->get('bulan', now()->month);
        $tahun   = $request->get('tahun', now()->year);

        $sjHariIni = SuratJalanHeader::with([
                'armada','sopir','kernet',
                'kitirDetail.kitir.spbe',
                'details.pangkalan',
                'details.sisaDistribusi',
            ])
            ->where('tanggal', $tanggal)
            ->whereIn('status', ['aktif','selesai'])
            ->orderBy('nomor_urut')
            ->get();

        // Load referensi pengalihan secara terpisah (polymorphic tidak support eager load)
        $allSisa = $sjHariIni->flatMap->details->flatMap->sisaDistribusi;
        $alihIds = $allSisa->where('tipe','alih_pangkalan')->pluck('referensi_id')->filter();
        $alihMap = SjPengalihan::with('pangkalan')->whereIn('id', $alihIds)->get()->keyBy('id');

        // Stok armada aktif (gendongan) per armada
        $stokArmada = StokArmada::with('armada')
            ->where('sisa_akhir', '>', 0)
            ->get()
            ->groupBy('armada_id');

        // Stok gudang tersedia
        $stokGudang = GudangStok::where('agen_id', Agen::profil()?->id ?? 0)
            ->where('sisa_stok', '>', 0)
            ->sum('sisa_stok');

        $pangkalans = Pangkalan::aktif()->orderBy('nama_pangkalan')->get();
        $agenLain   = Agen::where('id', '!=', Agen::profil()?->id ?? 0)->get();

        $bulanList = collect(range(1,12))->mapWithKeys(fn($m) =>
            [$m => Carbon::create()->month($m)->translatedFormat('F')]
        );

        return view('agen.distribusi.realisasi', compact(
            'sjHariIni','stokArmada','stokGudang','alihMap',
            'tanggal','bulan','tahun','bulanList',
            'pangkalans','agenLain'
        ));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // UPDATE — Input qty terima per pangkalan
    // ─────────────────────────────────────────────────────────────────────────
    public function update(Request $request, SuratJalanDetail $detail)
    {
        $request->validate([
            'qty_terima'                        => 'required|integer|min:0',
            'keterangan'                        => 'nullable|string|max:255',
            'sisa_alih.*.pangkalan_id'          => 'nullable|exists:pangkalans,id',
            'sisa_alih.*.qty'                   => 'nullable|integer|min:1',
            'sisa.armada.qty'                   => 'nullable|integer|min:0',
            'sisa.gudang.qty'                   => 'nullable|integer|min:0',
            'sisa.agen_lain.agen_tujuan_id'     => 'nullable|exists:agens,id',
            'sisa.agen_lain.qty'                => 'nullable|integer|min:0',
            'sisa.agen_lain.qty_kosong'         => 'nullable|integer|min:0',
        ]);

        $qtyTerima = (int)$request->qty_terima;
        $qtyMaks   = max($detail->qty_jadwal, $detail->qty_maks ?? $detail->qty_jadwal);

        if ($qtyTerima > $qtyMaks) {
            $selisih = $qtyTerima - $detail->qty_jadwal;
            return back()->withErrors([
                'qty_terima' => "Qty melebihi jadwal ({$detail->qty_jadwal}) sebesar {$selisih} tabung. "
                    . "Tambah sumber kelebihan dulu (gendongan/gudang/pengalihan masuk)."
            ]);
        }

        $sisaTotal = $qtyMaks - $qtyTerima;

        // Bangun $sisaRows dari format form baru
        $sisaRows = [];

        // Pengalihan ke pangkalan
        foreach ($request->input('sisa_alih', []) as $row) {
            $qty = (int)($row['qty'] ?? 0);
            if (!empty($row['pangkalan_id']) && $qty > 0) {
                $sisaRows[] = [
                    'tipe'         => 'alih_pangkalan',
                    'qty'          => $qty,
                    'pangkalan_id' => $row['pangkalan_id'],
                ];
            }
        }

        // Tetap di armada
        $qtyArmada = (int)$request->input('sisa.armada.qty', 0);
        if ($qtyArmada > 0) {
            $sisaRows[] = ['tipe' => 'stok_armada', 'qty' => $qtyArmada];
        }

        // Gudang sendiri
        $qtyGudang = (int)$request->input('sisa.gudang.qty', 0);
        if ($qtyGudang > 0) {
            $sisaRows[] = ['tipe' => 'gudang_sendiri', 'qty' => $qtyGudang];
        }

        // Titip agen lain
        $qtyAgenLain = (int)$request->input('sisa.agen_lain.qty', 0);
        if ($qtyAgenLain > 0) {
            $sisaRows[] = [
                'tipe'           => 'titip_agen_lain',
                'qty'            => $qtyAgenLain,
                'agen_tujuan_id' => $request->input('sisa.agen_lain.agen_tujuan_id'),
                'qty_kosong'     => (int)$request->input('sisa.agen_lain.qty_kosong', 0),
            ];
        }

        // Validasi total
        $totalDilaporkan = array_sum(array_column($sisaRows, 'qty'));
        if ($sisaTotal > 0 && $totalDilaporkan !== $sisaTotal) {
            return back()->withErrors([
                'sisa' => "Total sisa harus {$sisaTotal} tabung. Saat ini: {$totalDilaporkan}."
            ]);
        }

        DB::transaction(function () use ($request, $detail, $qtyTerima, $sisaTotal, $sisaRows) {
            $agen = Agen::profil();

            // 1. Update detail SJ
            $status = 'terkirim';
            if ($qtyTerima <= 0 && $sisaTotal > 0)      $status = 'batal';
            elseif ($qtyTerima < $detail->qty_jadwal)    $status = 'sebagian';

            $detail->update([
                'qty_terima'  => $qtyTerima,
                'status'      => $status,
                'keterangan'  => $request->keterangan,
            ]);

            // 2. Hapus sisa lama, proses baru
            $detail->sisaDistribusi()->each(function ($s) {
                // Rollback referensi lama jika perlu
                $this->rollbackSisa($s);
                $s->delete();
            });

            // 3. Proses setiap baris sisa
            foreach ($sisaRows as $i => $row) {
                $tipe = $row['tipe'];
                $qty  = (int)$row['qty'];
                $refId = null;

                switch ($tipe) {
                    case 'alih_pangkalan':
                        $alih = SjPengalihan::create([
                            'sj_detail_id' => $detail->id,
                            'pangkalan_id' => $row['pangkalan_id'],
                            'qty'          => $qty,
                            'urutan'       => $i + 1,
                            'status'       => 'pending',
                            'keterangan'   => $row['keterangan'] ?? null,
                        ]);
                        $refId = $alih->id;
                        $detail->increment('qty_dialihkan', $qty);

                        // Auto buat SjDetailTambahan di pangkalan tujuan
                        $detailTujuan = SuratJalanDetail::where('header_id', $detail->header_id)
                            ->where('pangkalan_id', $row['pangkalan_id'])
                            ->first();
                        if ($detailTujuan) {
                            SjDetailTambahan::updateOrCreate(
                                ['sj_detail_id' => $detailTujuan->id, 'sumber_sj_detail_id' => $detail->id],
                                ['qty' => $qty, 'sumber_tipe' => 'pengalihan_pangkalan',
                                 'keterangan' => 'Dari '.$detail->pangkalan?->nama_pangkalan]
                            );
                            $newMaks = $detailTujuan->qty_jadwal
                                + SjDetailTambahan::where('sj_detail_id', $detailTujuan->id)->sum('qty');
                            $detailTujuan->update([
                                'qty_maks'     => $newMaks,
                                'qty_tambahan' => $newMaks - $detailTujuan->qty_jadwal,
                            ]);
                        }
                        break;

                    case 'gudang_sendiri':
                        $gudang = GudangStok::create([
                            'agen_id'      => $agen->id,
                            'sumber'       => 'sisa_sj',
                            'sj_header_id' => $detail->header_id,
                            'tgl_masuk'    => $detail->header->tanggal,
                            'qty_masuk'    => $qty,
                            'qty_keluar'   => 0,
                            'sisa_stok'    => $qty,
                            'keterangan'   => 'Sisa SJ '.$detail->header->no_sj
                                             .' dari '.$detail->pangkalan?->nama_pangkalan,
                        ]);
                        $refId = $gudang->id;
                        break;

                    case 'titip_agen_lain':
                        // Buat record gudang di agen tujuan
                        $gudangTitip = GudangStok::create([
                            'agen_id'      => $row['agen_tujuan_id'],
                            'sumber'       => 'titipan_agen',
                            'agen_asal_id' => $agen->id,
                            'sj_header_id' => $detail->header_id,
                            'tgl_masuk'    => $detail->header->tanggal,
                            'qty_masuk'    => $qty,
                            'qty_keluar'   => 0,
                            'sisa_stok'    => $qty,
                            'keterangan'   => 'Titipan dari agen '.$agen->nama_agen,
                        ]);
                        // Buat transaksi antar agen
                        $transaksi = TransaksiAntarAgen::create([
                            'agen_asal_id'   => $agen->id,
                            'agen_tujuan_id' => $row['agen_tujuan_id'],
                            'qty_tabung_isi' => $qty,
                            'qty_tabung_kosong' => (int)($row['qty_kosong'] ?? 0),
                            'tgl_titip'      => $detail->header->tanggal,
                            'status'         => 'aktif',
                            'gudang_stok_id' => $gudangTitip->id,
                            'sj_header_id'   => $detail->header_id,
                        ]);
                        $refId = $transaksi->id;
                        break;

                    case 'stok_armada':
                        // Akan diupdate saat tutup trip, hanya catat dulu
                        $refId = null;
                        break;
                }

                SjSisaDistribusi::create([
                    'sj_detail_id'    => $detail->id,
                    'qty'             => $qty,
                    'tipe'            => $tipe,
                    'referensi_id'    => $refId,
                    'referensi_tipe'  => $tipe,
                    'keterangan'      => $row['keterangan'] ?? null,
                ]);
            }

            // 4. Cek apakah semua detail SJ sudah selesai
            $header       = $detail->fresh()->header;
            $belumSelesai = $header->details()
                ->whereNotIn('status', ['terkirim','sebagian','dialihkan','batal'])
                ->count();
            if ($belumSelesai === 0) {
                $this->tutupTrip($header);
            }
        });

        return back()->with('success',
            "{$detail->pangkalan?->nama_pangkalan}: {$qtyTerima} diterima"
            .($sisaTotal > 0 ? ", {$sisaTotal} sisa sudah diproses." : ".")
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // TUTUP TRIP — Setelah semua pangkalan selesai, hitung stok_armada
    // ─────────────────────────────────────────────────────────────────────────
    private function tutupTrip(SuratJalanHeader $header): void
    {
        $header->load(['details.sisaDistribusi']);

        $totalTerkirim = $header->details->sum('qty_terima');
        $totalGudang   = $header->details->flatMap->sisaDistribusi
            ->whereIn('tipe', ['gudang_sendiri','titip_agen_lain'])
            ->sum('qty');
        $totalAlih     = $header->details->flatMap->sisaDistribusi
            ->where('tipe', 'alih_pangkalan')
            ->sum('qty');
        $sisaArmada    = $header->details->flatMap->sisaDistribusi
            ->where('tipe', 'stok_armada')
            ->sum('qty');

        // Upsert stok_armada
        $stok = StokArmada::updateOrCreate(
            ['armada_id' => $header->armada_id, 'sj_header_id' => $header->id],
            [
                'tanggal'          => $header->tanggal,
                'gendongan_masuk'  => $header->qty_gendongan_masuk ?? 0,
                'ambil_do'         => $header->qty_refil,
                'ambil_gudang'     => $header->qty_ambil_gudang ?? 0,
                'total_terkirim'   => $totalTerkirim,
                'turun_gudang'     => $totalGudang,
                'sisa_akhir'       => $sisaArmada,
                'status'           => $sisaArmada > 0 ? 'ada_sisa' : 'selesai',
            ]
        );

        // Update referensi_id untuk baris stok_armada
        SjSisaDistribusi::whereIn('sj_detail_id', $header->details->pluck('id'))
            ->where('tipe', 'stok_armada')
            ->update(['referensi_id' => $stok->id, 'referensi_tipe' => 'stok_armada']);

        // Update status header
        $header->update(['status' => 'selesai']);
        if ($header->kitirDetail) {
            $header->kitirDetail->update(['status' => 'diambil']);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Rollback referensi saat data diubah
    // ─────────────────────────────────────────────────────────────────────────
    private function rollbackSisa(SjSisaDistribusi $sisa): void
    {
        match($sisa->tipe) {
            'gudang_sendiri' => GudangStok::find($sisa->referensi_id)?->delete(),
            'titip_agen_lain' => (function () use ($sisa) {
                $t = TransaksiAntarAgen::find($sisa->referensi_id);
                if ($t) {
                    GudangStok::find($t->gudang_stok_id)?->delete();
                    $t->delete();
                }
            })(),
            'alih_pangkalan' => (function () use ($sisa) {
                // Hapus pengalihan
                $alih = SjPengalihan::find($sisa->referensi_id);
                if ($alih) {
                    // Rollback SjDetailTambahan di pangkalan tujuan
                    $tambahan = SjDetailTambahan::where('sumber_sj_detail_id', $sisa->sj_detail_id)
                        ->where('sumber_tipe', 'pengalihan_pangkalan')
                        ->first();
                    if ($tambahan) {
                        $detailTujuan = SuratJalanDetail::find($tambahan->sj_detail_id);
                        $tambahan->delete();
                        if ($detailTujuan) {
                            // Recalculate qty_maks
                            $newMaks = $detailTujuan->qty_jadwal
                                + SjDetailTambahan::where('sj_detail_id', $detailTujuan->id)->sum('qty');
                            $detailTujuan->update([
                                'qty_maks'     => $newMaks,
                                'qty_tambahan' => max(0, $newMaks - $detailTujuan->qty_jadwal),
                            ]);
                        }
                    }
                    $alih->delete();
                }
            })(),
            default => null,
        };
    }

    // ─────────────────────────────────────────────────────────────────────────
    // LAPORAN BULANAN
    // ─────────────────────────────────────────────────────────────────────────
    public function laporan(Request $request)
    {
        $bulan = $request->get('bulan', now()->month);
        $tahun = $request->get('tahun', now()->year);

        $rekapHarian = SuratJalanHeader::whereYear('tanggal', $tahun)
            ->whereMonth('tanggal', $bulan)
            ->whereIn('status', ['aktif','selesai'])
            ->with(['details','stokArmada'])
            ->orderBy('tanggal')
            ->get()
            ->groupBy(fn($sj) => $sj->tanggal->format('d/m'))
            ->map(fn($items) => [
                'ambil_do'         => $items->sum('qty_refil'),
                'gendongan_masuk'  => $items->sum('qty_gendongan_masuk'),
                'ambil_gudang'     => $items->sum('qty_ambil_gudang'),
                'total_tersedia'   => $items->sum(fn($s) =>
                    $s->qty_refil + ($s->qty_gendongan_masuk??0) + ($s->qty_ambil_gudang??0)),
                'terkirim'         => $items->flatMap->details->sum('qty_terima'),
                'gendongan_keluar' => $items->flatMap->stokArmada->sum('sisa_akhir'),
                'gudang'           => $items->flatMap->stokArmada->sum('turun_gudang'),
            ]);

        $rekapPangkalan = SjSisaDistribusi::with(['detail.pangkalan'])
            ->whereHas('detail.header', fn($q) =>
                $q->whereYear('tanggal', $tahun)->whereMonth('tanggal', $bulan)
            )
            ->get()
            ->groupBy('sj_detail_id');

        $bulanList = collect(range(1,12))->mapWithKeys(fn($m) =>
            [$m => Carbon::create()->month($m)->translatedFormat('F')]
        );

        return view('agen.distribusi.laporan', compact(
            'rekapHarian','rekapPangkalan','bulan','tahun','bulanList'
        ));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // STOK — Monitoring gendongan & gudang + form ambil
    // ─────────────────────────────────────────────────────────────────────────
    public function stok(Request $request)
    {
        $agen = Agen::profil();

        // Gendongan aktif per armada (sisa_akhir > 0)
        // Gendongan HANYA bisa dipakai armada yang SAMA
        // Jika ingin pindah ke armada lain, harus lewat gudang dulu
        $gendongan = StokArmada::with(['armada','sjHeader'])
            ->where('sisa_akhir', '>', 0)
            ->orderByDesc('tanggal')
            ->get()
            ->groupBy('armada_id');

        // Stok gudang tersedia
        $gudang = GudangStok::with('agenAsal')
            ->where('agen_id', $agen?->id ?? 0)
            ->where('sisa_stok', '>', 0)
            ->orderBy('tgl_masuk')
            ->get();

        // Transaksi antar agen yang masih aktif
        $titipanAktif = TransaksiAntarAgen::with(['agenAsal','agenTujuan'])
            ->where(fn($q) => $q
                ->where('agen_asal_id', $agen?->id)
                ->orWhere('agen_tujuan_id', $agen?->id)
            )
            ->where('status', 'aktif')
            ->orderByDesc('tgl_titip')
            ->get();

        return view('agen.distribusi.stok', compact(
            'gendongan','gudang','titipanAktif'
        ));
    }

    /** Ambil stok gudang untuk dipakai di SJ */
    public function ambilGudang(Request $request)
    {
        $request->validate([
            'sj_header_id' => 'required|exists:surat_jalan_headers,id',
            'qty'          => 'required|integer|min:1',
        ]);

        $agen  = Agen::profil();
        $total = GudangStok::where('agen_id', $agen->id)
                     ->where('sisa_stok', '>', 0)->sum('sisa_stok');

        if ((int)$request->qty > $total) {
            return back()->withErrors(['msg' => "Stok gudang hanya {$total} tabung."]);
        }

        DB::transaction(function () use ($request, $agen) {
            $qty = (int)$request->qty;
            $sj  = SuratJalanHeader::findOrFail($request->sj_header_id);

            // Kurangi gudang FIFO
            GudangStok::kurangi($agen->id, $qty, "Diambil untuk SJ {$sj->no_sj}");

            // Update SJ header
            $sj->increment('qty_ambil_gudang', $qty);

            // Update stok_armada — hanya increment ambil_gudang, tidak overwrite data lain
            $existingStok = StokArmada::where('armada_id', $sj->armada_id)
                ->where('sj_header_id', $sj->id)->first();
            if ($existingStok) {
                $existingStok->increment('ambil_gudang', $qty);
            } else {
                StokArmada::create([
                    'armada_id'    => $sj->armada_id,
                    'sj_header_id' => $sj->id,
                    'tanggal'      => $sj->tanggal,
                    'ambil_do'     => $sj->qty_refil,
                    'ambil_gudang' => $qty,
                ]);
            }
        });

        return back()->with('success', "{$request->qty} tabung berhasil diambil dari gudang.");
    }

    /** Konfirmasi gendongan masuk ke SJ baru */
    public function konfirmasiGendongan(Request $request)
    {
        $request->validate([
            'sj_header_id'        => 'required|exists:surat_jalan_headers,id',
            'stok_armada_id'      => 'required|exists:stok_armada,id',
            'qty_gendongan_masuk' => 'required|integer|min:1',
        ]);

        $stok = StokArmada::findOrFail($request->stok_armada_id);
        $sjBaru = SuratJalanHeader::findOrFail($request->sj_header_id);

        // Validasi: gendongan hanya boleh dipakai armada yang SAMA
        if ($stok->armada_id !== $sjBaru->armada_id) {
            return back()->withErrors([
                'qty' => "Gendongan armada {$stok->armada?->no_polisi} tidak bisa dipakai "
                    . "armada {$sjBaru->armada?->no_polisi}. "
                    . "Turunkan ke gudang dulu, lalu ambil gudang dari SJ armada lain."
            ]);
        }

        if ((int)$request->qty_gendongan_masuk > $stok->sisa_akhir) {
            return back()->withErrors([
                'qty' => "Gendongan armada {$stok->armada?->no_polisi} hanya {$stok->sisa_akhir} tabung."
            ]);
        }

        DB::transaction(function () use ($request, $stok, $sjBaru) {
            // Update SJ header dengan gendongan masuk
            $sjBaru->update(['qty_gendongan_masuk' => $request->qty_gendongan_masuk]);

            // Kurangi sisa gendongan dari trip sebelumnya
            $stok->decrement('sisa_akhir', $request->qty_gendongan_masuk);
            if ($stok->fresh()->sisa_akhir <= 0) {
                $stok->update(['status' => 'selesai']);
            }

            // Update / buat stok armada SJ baru
            StokArmada::updateOrCreate(
                ['armada_id' => $sjBaru->armada_id, 'sj_header_id' => $sjBaru->id],
                [
                    'tanggal'         => $sjBaru->tanggal,
                    'gendongan_masuk' => $request->qty_gendongan_masuk,
                    'ambil_do'        => $sjBaru->qty_refil,
                ]
            );
        });

        return back()->with('success',
            "{$request->qty_gendongan_masuk} tabung gendongan masuk ke SJ."
        );
    }

    /** Tandai transaksi antar agen selesai */
    public function selesaiAntarAgen(Request $request, TransaksiAntarAgen $transaksi)
    {
        $transaksi->update([
            'status'             => 'selesai',
            'tgl_ambil_kembali'  => now()->toDateString(),
        ]);
        return back()->with('success', 'Transaksi antar agen ditandai selesai.');
    }
}
