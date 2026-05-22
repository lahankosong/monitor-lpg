<?php
namespace App\Http\Controllers\Agen;

use App\Http\Controllers\Controller;
use App\Services\JurnalService;
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

        // Stok armada aktif (gendongan) per armada — ROLE-AWARE
        $user = auth()->user();
        $gendonganQuery = StokArmada::with(['armada','sjHeader'])
            ->where('sisa_akhir', '>', 0)
            ->orderByDesc('tanggal');

        if ($user->role === 'driver' && $user->karyawan_id) {
            // Driver: hanya armada yang dia kendarai
            $armadaIds = \Illuminate\Support\Facades\DB::table('armadas')
                ->where('sopir_id', $user->karyawan_id)
                ->pluck('id');
            $gendonganQuery->whereIn('armada_id', $armadaIds);
        }
        // Admin/manajer/direktur: semua armada (tidak perlu filter)

        $stokArmada = $gendonganQuery->get()->groupBy('armada_id');

        // Stok gudang tersedia
        $stokGudang = GudangStok::where('agen_id', Agen::profil()?->id ?? 0)
            ->where('sisa_stok', '>', 0)
            ->sum('sisa_stok');

        // Buffer gudang tabung kosong
        $bufferKosong = (int)(
            \Illuminate\Support\Facades\DB::table('gudang_tabung_kosong')->where('jenis','masuk')->sum('qty') -
            \Illuminate\Support\Facades\DB::table('gudang_tabung_kosong')->where('jenis','keluar')->sum('qty')
        );

        $pangkalans = Pangkalan::aktif()->orderBy('nama_pangkalan')->get();
        $agenLain   = Agen::where('id', '!=', Agen::profil()?->id ?? 0)->get();

        $bulanList = collect(range(1,12))->mapWithKeys(fn($m) =>
            [$m => Carbon::create()->month($m)->translatedFormat('F')]
        );

        return view('agen.distribusi.realisasi', compact(
            'sjHariIni','stokArmada','stokGudang','bufferKosong','alihMap',
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
    /** Tutup trip langsung (jika sisa = 0) */
    public function tutupTripDirect(SuratJalanHeader $sj)
    {
        $totalTersedia = $sj->qty_refil + ($sj->qty_gendongan_masuk ?? 0) + ($sj->qty_ambil_gudang ?? 0);
        $totalTerkirim = $sj->details->sum('qty_terima');
        if ($totalTersedia - $totalTerkirim > 0) {
            return back()->withErrors(['sisa' => 'Masih ada sisa — tentukan nasib sisa dulu.']);
        }
        $this->tutupTrip($sj);
        return back()->with('success', "Trip {$sj->no_sj} berhasil ditutup.");
    }

    /** Simpan nasib sisa trip — dipanggil sebelum tutup trip */
    public function nasibSisaTrip(Request $request, SuratJalanHeader $sj)
    {
        $request->validate([
            'sisa_tipe'   => 'required|array|min:1',
            'sisa_qty'    => 'required|array',
        ]);

        $sisaTypes = $request->sisa_tipe;
        $sisaQty   = $request->sisa_qty;

        // Hitung total sisa sesungguhnya
        $totalTersedia = $sj->qty_refil
                       + ($sj->qty_gendongan_masuk ?? 0)
                       + ($sj->qty_ambil_gudang ?? 0);
        $totalTerkirim = $sj->details->sum('qty_terima');
        $sisaTrip      = max(0, $totalTersedia - $totalTerkirim);

        // Validasi total alokasi = sisa
        $totalAlokasi = collect($sisaTypes)->sum(fn($t) => (int)($sisaQty[$t] ?? 0));
        if ($totalAlokasi !== $sisaTrip) {
            return back()->withErrors([
                'sisa' => "Total alokasi ({$totalAlokasi}) ≠ sisa trip ({$sisaTrip}). Periksa kembali."
            ]);
        }

        // Hapus sisa lama untuk SJ ini (jika ada edit)
        SjSisaDistribusi::whereHas('sjDetail', fn($q) => $q->where('header_id', $sj->id))
            ->whereIn('tipe', ['stok_armada','gudang_sendiri','titip_agen_lain'])
            ->delete();

        // Simpan sisa baru — attached ke detail pertama (karena ini level SJ)
        $detailPertama = $sj->details->first();
        if (!$detailPertama) return back()->withErrors(['sisa' => 'SJ tidak punya detail.']);

        foreach ($sisaTypes as $tipe) {
            $qty = (int)($sisaQty[$tipe] ?? 0);
            if ($qty <= 0) continue;

            $data = [
                'sj_detail_id' => $detailPertama->id,
                'tipe'         => $tipe,
                'qty'          => $qty,
                'keterangan'   => 'Nasib sisa trip '.$sj->no_sj,
            ];

            if ($tipe === 'titip_agen_lain') {
                $data['referensi_id']   = $request->sisa_agen_id;
                $data['referensi_tipe'] = 'agen';
            }

            SjSisaDistribusi::create($data);
        }

        // Langsung tutup trip
        $this->tutupTrip($sj);

        return back()->with('success', "Trip {$sj->no_sj} berhasil ditutup.");
    }

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

        // ── Jurnal Buku Besar otomatis ─────────────────────────────
        // Debit 1003 Piutang Dagang, Kredit 4001 Penjualan Refil
        // Debit 5001 HPP Tebusan, Kredit 1005 Persediaan Tabung
        try {
            $hargaJual  = (int) (\DB::table('harga_referensis')
                ->where('kategori', 'jual_pangkalan')
                ->where('is_aktif', true)
                ->orderByDesc('berlaku_mulai')
                ->value('harga') ?? 0);

            $hargaTebus = (int) (\DB::table('harga_referensis')
                ->where('kategori', 'tebus_refil')
                ->where('is_aktif', true)
                ->orderByDesc('berlaku_mulai')
                ->value('harga') ?? 0);

            if ($hargaJual > 0 && $hargaTebus > 0 && $totalTerkirim > 0) {
                app(JurnalService::class)->distribusi(
                    \Carbon\Carbon::parse($header->tanggal),
                    $totalTerkirim,
                    $hargaJual,
                    $hargaTebus,
                    $header->no_sj ?? 'SJ-'.$header->id,
                    $header->id
                );
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('[Distribusi] Gagal buat jurnal: '.$e->getMessage());
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

    // ─────────────────────────────────────────────────────────────
    // SIMPAN REALISASI FLEKSIBEL
    // Terima input bebas: pangkalan jadwal + di luar jadwal
    // Backend rekonsiliasi otomatis
    // ─────────────────────────────────────────────────────────────
    public function simpanRealisasi(Request $request, SuratJalanHeader $sj)
    {
        $request->validate([
            'pangkalan_id'  => 'required|array',
            'qty_terima'    => 'required|array',
            'sisa_armada'   => 'nullable|integer|min:0',
            'sisa_gudang'   => 'nullable|integer|min:0',
            'sisa_agen_lain'=> 'nullable|integer|min:0',
        ]);

        $pangkalanIds  = $request->pangkalan_id;
        $qtys          = $request->qty_terima;
        $totalTersedia = $sj->qty_refil
                       + ($sj->qty_gendongan_masuk ?? 0)
                       + ($sj->qty_ambil_gudang ?? 0);

        $totalTerkirim = collect($qtys)->sum(fn($q) => (int)$q);
        $sisaTrip      = max(0, $totalTersedia - $totalTerkirim);

        // Validasi nasib sisa
        $sisaArmada  = (int)$request->sisa_armada;
        $sisaGudang  = (int)$request->sisa_gudang;
        $sisaAgenLain= (int)$request->sisa_agen_lain;
        $totalSisa   = $sisaArmada + $sisaGudang + $sisaAgenLain;

        if ($totalSisa !== $sisaTrip) {
            return back()->withErrors([
                'sisa' => "Total nasib sisa ({$totalSisa}) ≠ sisa trip ({$sisaTrip}). Periksa kembali."
            ]);
        }

        DB::transaction(function () use (
            $sj, $pangkalanIds, $qtys,
            $sisaArmada, $sisaGudang, $sisaAgenLain, $sisaTrip, $totalTerkirim
        ) {
            // ── Update / buat detail per pangkalan ───────────────
            $jadwalIds = $sj->details->pluck('pangkalan_id')->toArray();

            foreach ($pangkalanIds as $i => $pkId) {
                $qty = (int)($qtys[$i] ?? 0);
                if (!$pkId) continue;

                // Cari detail yang sudah ada
                $detail = $sj->details->firstWhere('pangkalan_id', $pkId);

                if ($detail) {
                    // Update detail yang ada
                    $status = 'terkirim';
                    if ($qty <= 0)                    $status = 'batal';
                    elseif ($qty < $detail->qty_jadwal) $status = 'sebagian';

                    $detail->update([
                        'qty_terima' => $qty,
                        'status'     => $status,
                    ]);

                    // Hapus sisa lama
                    $detail->sisaDistribusi()
                        ->whereNotIn('tipe',['alih_pangkalan'])
                        ->delete();

                } else {
                    // Pangkalan di luar jadwal → tambah detail baru
                    // Rekonsiliasi: cari jadwal yang belum penuh untuk jadi sumber
                    $detail = SuratJalanDetail::create([
                        'header_id'   => $sj->id,
                        'pangkalan_id'=> $pkId,
                        'qty_jadwal'  => 0,  // tidak ada jadwal
                        'qty_terima'  => $qty,
                        'status'      => $qty > 0 ? 'terkirim' : 'batal',
                        'urutan'      => $sj->details->count() + 1,
                        'is_extra'    => true,
                    ]);
                }
            }

            // ── REKONSILIASI OTOMATIS ────────────────────────────
            // Pangkalan jadwal yang qty_terima < qty_jadwal
            // → sisa otomatis dicatat sebagai "tidak terambil"
            // → sistem cek apakah ada pangkalan ekstra yang menyerap
            $sj->refresh()->load('details');

            // Total sisa per jadwal
            foreach ($sj->details->where('is_extra', false) as $d) {
                $sisaDetail = max(0, $d->qty_jadwal - $d->qty_terima);
                if ($sisaDetail <= 0) continue;

                // Cari apakah ada baris ekstra yang "menyerap" sisa ini
                // Rekonsiliasi: distribusikan sisa ke ekstra secara FIFO
                $ekstraRows = $sj->details->where('is_extra', true)
                    ->where('qty_terima', '>', 0);

                $tersisaAlih = $sisaDetail;
                foreach ($ekstraRows as $ekstra) {
                    if ($tersisaAlih <= 0) break;
                    $serap = min($tersisaAlih, $ekstra->qty_terima);

                    // Catat pengalihan otomatis
                    SjSisaDistribusi::updateOrCreate(
                        ['sj_detail_id' => $d->id, 'tipe' => 'alih_pangkalan',
                         'referensi_id' => $ekstra->pangkalan_id],
                        ['qty' => $serap,
                         'keterangan' => 'Otomatis: diserap pangkalan di luar jadwal']
                    );
                    $tersisaAlih -= $serap;
                }
            }

            // ── Simpan nasib sisa trip ───────────────────────────
            // Hapus sisa trip level lama
            SjSisaDistribusi::whereHas('sjDetail', fn($q) =>
                $q->where('header_id', $sj->id)
            )->whereIn('tipe',['stok_armada','gudang_sendiri','titip_agen_lain'])->delete();

            $detailPertama = $sj->details->first();
            if ($sisaArmada > 0) {
                SjSisaDistribusi::create([
                    'sj_detail_id' => $detailPertama->id,
                    'tipe'         => 'stok_armada',
                    'qty'          => $sisaArmada,
                    'keterangan'   => 'Sisa trip ' . $sj->no_sj,
                ]);
            }
            if ($sisaGudang > 0) {
                SjSisaDistribusi::create([
                    'sj_detail_id' => $detailPertama->id,
                    'tipe'         => 'gudang_sendiri',
                    'qty'          => $sisaGudang,
                    'keterangan'   => 'Turun ke gudang dari trip ' . $sj->no_sj,
                ]);
            }

            // Tutup trip
            $this->tutupTrip($sj->fresh());
        });

        return back()->with('success', "Trip {$sj->no_sj} berhasil disimpan dan ditutup.");
    }

    /** Turunkan gendongan ke gudang (tanpa masuk ke SJ baru) */
    public function turunGudang(Request $request)
    {
        $request->validate(['armada_id' => 'required|exists:armadas,id']);
        $stok = StokArmada::where('armada_id', $request->armada_id)
            ->where('sisa_akhir', '>', 0)->first();
        if (!$stok) return back()->withErrors(['msg' => 'Tidak ada gendongan aktif.']);

        DB::table('gudang_tabung_isi')->insert([
            'jenis'      => 'masuk',
            'sumber'     => 'turun_armada',
            'qty'        => $stok->sisa_akhir,
            'tanggal'    => now()->toDateString(),
            'armada_id'  => $request->armada_id,
            'keterangan' => 'Gendongan turun ke gudang sebelum DO baru',
            'created_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $stok->update(['sisa_akhir' => 0, 'status' => 'selesai']);
        return back()->with('success', "{$stok->sisa_akhir} tabung isi diturunkan ke gudang.");
    }
