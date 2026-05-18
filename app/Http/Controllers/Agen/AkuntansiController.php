<?php
namespace App\Http\Controllers\Agen;

use App\Http\Controllers\Controller;
use App\Models\Pangkalan;
use App\Models\HargaReferensi;
use Carbon\Carbon;
use App\Services\JurnalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AkuntansiController extends Controller
{
    // ─────────────────────────────────────────────────────────────────
    // DASHBOARD AKUNTANSI
    // ─────────────────────────────────────────────────────────────────
    public function dashboard(Request $request)
    {
        $bulan = $request->get('bulan', now()->month);
        $tahun = $request->get('tahun', now()->year);

        // Rekap piutang kerjasama
        $piutangRekap = DB::table('piutang_kerjasama')
            ->whereYear('bulan_tagih', $tahun)
            ->whereMonth('bulan_tagih', $bulan)
            ->selectRaw("
                SUM(total_tagihan) as total_tagihan,
                SUM(total_bayar)   as total_bayar,
                SUM(sisa_tagihan)  as sisa_tagihan,
                SUM(CASE WHEN status='lunas'      THEN 1 ELSE 0 END) as jml_lunas,
                SUM(CASE WHEN status='belum_bayar' THEN 1 ELSE 0 END) as jml_belum,
                SUM(CASE WHEN status='sebagian'    THEN 1 ELSE 0 END) as jml_sebagian,
                COUNT(*) as total_tagihan_cnt
            ")->first();

        // Rekap kas kecil bulan ini
        $kasRekap = DB::table('kas_kecil')
            ->whereYear('tanggal', $tahun)
            ->whereMonth('tanggal', $bulan)
            ->selectRaw("
                SUM(CASE WHEN jenis='masuk'  THEN jumlah ELSE 0 END) as total_masuk,
                SUM(CASE WHEN jenis='keluar' THEN jumlah ELSE 0 END) as total_keluar,
                COUNT(*) as total_transaksi
            ")->first();

        // Saldo kas kecil kumulatif
        $saldoKas = DB::table('kas_kecil')
            ->whereDate('tanggal', '<=', Carbon::create($tahun, $bulan)->endOfMonth())
            ->selectRaw("
                SUM(CASE WHEN jenis='masuk'  THEN jumlah ELSE 0 END) -
                SUM(CASE WHEN jenis='keluar' THEN jumlah ELSE 0 END) as saldo
            ")->value('saldo') ?? 0;

        // Jatuh tempo bulan ini yang belum lunas
        $jatuhTempo = DB::table('piutang_kerjasama as pk')
            ->join('pangkalans as p', 'pk.pangkalan_id', '=', 'p.id')
            ->whereIn('pk.status', ['belum_bayar','sebagian'])
            ->whereYear('pk.jatuh_tempo', $tahun)
            ->whereMonth('pk.jatuh_tempo', $bulan)
            ->select('pk.*', 'p.nama_pangkalan', 'p.no_reg')
            ->orderBy('pk.jatuh_tempo')
            ->get();

        $bulanList = collect(range(1,12))->mapWithKeys(fn($m) =>
            [$m => Carbon::create()->month($m)->translatedFormat('F')]
        );

        return view('agen.akuntansi.dashboard', compact(
            'piutangRekap','kasRekap','saldoKas',
            'jatuhTempo','bulan','tahun','bulanList'
        ));
    }

    // ─────────────────────────────────────────────────────────────────
    // PIUTANG KERJASAMA — list dan generate
    // ─────────────────────────────────────────────────────────────────
    public function piutang(Request $request)
    {
        $bulan  = $request->get('bulan', now()->month);
        $tahun  = $request->get('tahun', now()->year);
        $status = $request->get('status', '');

        $piutang = DB::table('piutang_kerjasama as pk')
            ->join('pangkalans as p', 'pk.pangkalan_id', '=', 'p.id')
            ->leftJoin('surat_jalan_headers as sj', 'pk.sj_header_id', '=', 'sj.id')
            ->whereYear('pk.bulan_tagih', $tahun)
            ->whereMonth('pk.bulan_tagih', $bulan)
            ->when($status, fn($q) => $q->where('pk.status', $status))
            ->select(
                'pk.*',
                'p.nama_pangkalan', 'p.no_reg', 'p.tipe as tipe_pangkalan',
                'sj.no_sj', 'sj.tanggal as tgl_sj'
            )
            ->orderBy('pk.jatuh_tempo')
            ->paginate(30)->withQueryString();

        // Summary
        $summary = DB::table('piutang_kerjasama')
            ->whereYear('bulan_tagih', $tahun)
            ->whereMonth('bulan_tagih', $bulan)
            ->selectRaw("
                SUM(total_tagihan) as tagihan,
                SUM(total_bayar)   as bayar,
                SUM(sisa_tagihan)  as sisa
            ")->first();

        $bulanList = collect(range(1,12))->mapWithKeys(fn($m) =>
            [$m => Carbon::create()->month($m)->translatedFormat('F')]
        );

        return view('agen.akuntansi.piutang-kerjasama', compact(
            'piutang','summary','bulan','tahun','bulanList','status'
        ));
    }

    /** Generate piutang dari SJ yang sudah selesai */
    public function generatePiutang(Request $request)
    {
        $request->validate([
            'bulan' => 'required|integer|min:1|max:12',
            'tahun' => 'required|integer',
        ]);

        $bulan = (int)$request->bulan;
        $tahun = (int)$request->tahun;
        $bulanTagih = Carbon::create($tahun, $bulan)->startOfMonth();
        $jatuhTempo = $bulanTagih->copy()->addDays(5); // jatuh tempo tgl 5

        // Harga kerjasama dari referensi harga
        $harga = DB::table('harga_referensis')
            ->where('kategori', 'sewa_tabung')
            ->where('is_aktif', true)
            ->orderByDesc('berlaku_mulai')
            ->value('harga') ?? 0;

        if (!$harga) {
            return back()->withErrors(['msg' => 'Harga kerjasama belum diset di Referensi Harga.']);
        }

        // Ambil SJ yang sudah selesai bulan sebelumnya, pangkalan kerjasama
        $bulanDist     = Carbon::create($tahun, $bulan)->subMonth();
        $sjSelesai = DB::table('surat_jalan_details as d')
            ->join('surat_jalan_headers as h', 'd.header_id', '=', 'h.id')
            ->join('pangkalans as p', 'd.pangkalan_id', '=', 'p.id')
            ->where('h.status', 'selesai')
            ->where('p.tipe', 'kerjasama')
            ->whereYear('h.tanggal', $bulanDist->year)
            ->whereMonth('h.tanggal', $bulanDist->month)
            ->where('d.qty_terima', '>', 0)
            ->select(
                'd.pangkalan_id', 'd.header_id as sj_header_id',
                'h.tanggal as tgl_distribusi', 'd.qty_terima'
            )
            ->get();

        $generated = 0;
        foreach ($sjSelesai as $row) {
            // Skip jika sudah ada
            $exists = DB::table('piutang_kerjasama')
                ->where('pangkalan_id', $row->pangkalan_id)
                ->where('sj_header_id', $row->sj_header_id)
                ->exists();
            if ($exists) continue;

            $total = $row->qty_terima * $harga;
            DB::table('piutang_kerjasama')->insert([
                'pangkalan_id'      => $row->pangkalan_id,
                'sj_header_id'      => $row->sj_header_id,
                'tanggal_distribusi'=> $row->tgl_distribusi,
                'bulan_tagih'       => $bulanTagih->toDateString(),
                'jatuh_tempo'       => $jatuhTempo->toDateString(),
                'qty_tabung'        => $row->qty_terima,
                'harga_per_tabung'  => $harga,
                'total_tagihan'     => $total,
                'total_bayar'       => 0,
                'sisa_tagihan'      => $total,
                'status'            => 'belum_bayar',
                'created_by'        => auth()->id(),
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);
            $generated++;
        }

        return back()->with('success',
            "{$generated} tagihan kerjasama berhasil di-generate untuk bulan {$bulanTagih->translatedFormat('F Y')}."
        );
    }

    /** Catat pembayaran piutang */
    public function bayarPiutang(Request $request, int $piutangId)
    {
        $request->validate([
            'jumlah'       => 'required|integer|min:1',
            'tanggal_bayar'=> 'required|date',
            'metode'       => 'required|in:tunai,transfer,briva',
            'referensi'    => 'nullable|string|max:50',
            'keterangan'   => 'nullable|string|max:255',
        ]);

        $piutang = DB::table('piutang_kerjasama')->findOrFail($piutangId);

        DB::transaction(function () use ($request, $piutangId, $piutang) {
            $jumlah = (int)$request->jumlah;

            // Catat pembayaran
            DB::table('piutang_kerjasama_bayar')->insert([
                'piutang_id'    => $piutangId,
                'pangkalan_id'  => $piutang->pangkalan_id,
                'tanggal_bayar' => $request->tanggal_bayar,
                'jumlah'        => $jumlah,
                'metode'        => $request->metode,
                'referensi'     => $request->referensi,
                'keterangan'    => $request->keterangan,
                'dicatat_oleh'  => auth()->id(),
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);

            // Update total bayar dan sisa
            $totalBayar = DB::table('piutang_kerjasama_bayar')
                ->where('piutang_id', $piutangId)->sum('jumlah');
            $sisa   = max(0, $piutang->total_tagihan - $totalBayar);
            $status = $sisa <= 0 ? 'lunas'
                    : ($totalBayar > 0 ? 'sebagian' : 'belum_bayar');

            DB::table('piutang_kerjasama')->where('id', $piutangId)->update([
                'total_bayar'    => $totalBayar,
                'sisa_tagihan'   => $sisa,
                'status'         => $status,
                'tanggal_lunas'  => $status === 'lunas' ? $request->tanggal_bayar : null,
                'updated_at'     => now(),
            ]);
        });

        // Jurnal buku besar: Debit 1001/1002 Kas, Kredit 4002 Pendapatan Kerjasama
        try {
            $metodePeta = ['tunai' => '1001', 'transfer' => '1002', 'briva' => '1002'];
            $akunKas    = $metodePeta[$request->metode] ?? '1001';
            app(JurnalService::class)->kerjasama(
                \Carbon\Carbon::parse($request->tanggal_bayar),
                (int)$request->jumlah,
                $akunKas,
                "Bayar kerjasama {$piutang->pangkalan_id} — ".number_format($request->jumlah),
                $piutangId
            );
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('[Kerjasama] Gagal jurnal: '.$e->getMessage());
        }

        return back()->with('success', 'Pembayaran kerjasama berhasil dicatat.');
    }

    // ─────────────────────────────────────────────────────────────────
    // KAS KECIL
    // ─────────────────────────────────────────────────────────────────
    public function kas(Request $request)
    {
        $bulan     = $request->get('bulan', now()->month);
        $tahun     = $request->get('tahun', now()->year);
        $kategori  = $request->get('kategori', '');

        $transaksi = DB::table('kas_kecil as k')
            ->leftJoin('users as u', 'k.created_by', '=', 'u.id')
            ->leftJoin('armadas as a', 'k.armada_id', '=', 'a.id')
            ->whereYear('k.tanggal', $tahun)
            ->whereMonth('k.tanggal', $bulan)
            ->when($kategori, fn($q) => $q->where('k.kategori', $kategori))
            ->select('k.*', 'u.name as nama_user', 'a.no_polisi')
            ->orderByDesc('k.tanggal')->orderByDesc('k.id')
            ->paginate(30)->withQueryString();

        // Summary bulan ini
        $summary = DB::table('kas_kecil')
            ->whereYear('tanggal', $tahun)
            ->whereMonth('tanggal', $bulan)
            ->selectRaw("
                SUM(CASE WHEN jenis='masuk'  THEN jumlah ELSE 0 END) as masuk,
                SUM(CASE WHEN jenis='keluar' THEN jumlah ELSE 0 END) as keluar,
                COUNT(*) as total_trx
            ")->first();

        // Saldo kumulatif
        $saldo = DB::table('kas_kecil')
            ->whereDate('tanggal', '<=', Carbon::create($tahun, $bulan)->endOfMonth())
            ->selectRaw("
                SUM(CASE WHEN jenis='masuk'  THEN jumlah ELSE 0 END) -
                SUM(CASE WHEN jenis='keluar' THEN jumlah ELSE 0 END) as saldo
            ")->value('saldo') ?? 0;

        // Rekap per kategori bulan ini
        $rekapKategori = DB::table('kas_kecil')
            ->whereYear('tanggal', $tahun)
            ->whereMonth('tanggal', $bulan)
            ->where('jenis', 'keluar')
            ->groupBy('kategori')
            ->selectRaw("kategori, SUM(jumlah) as total, COUNT(*) as jml")
            ->orderByRaw('SUM(jumlah) DESC')
            ->get();

        $armadas  = DB::table('armadas')->orderBy('no_polisi')->get();
        $bulanList = collect(range(1,12))->mapWithKeys(fn($m) =>
            [$m => Carbon::create()->month($m)->translatedFormat('F')]
        );

        return view('agen.akuntansi.kas-kecil', compact(
            'transaksi','summary','saldo','rekapKategori',
            'armadas','bulan','tahun','bulanList','kategori'
        ));
    }

    /** Simpan transaksi kas kecil */
    public function kasStore(Request $request)
    {
        $request->validate([
            'tanggal'    => 'required|date',
            'kategori'   => 'required|in:bbm_armada,gaji_karyawan,servis_armada,stnk_pajak,kantor,tabung,lain_lain',
            'keterangan' => 'required|string|max:255',
            'jumlah'     => 'required|integer|min:1',
            'jenis'      => 'required|in:masuk,keluar',
            'armada_id'  => 'nullable|exists:armadas,id',
        ]);

        DB::table('kas_kecil')->insert([
            'tanggal'    => $request->tanggal,
            'kategori'   => $request->kategori,
            'keterangan' => $request->keterangan,
            'jumlah'     => $request->jumlah,
            'jenis'      => $request->jenis,
            'armada_id'  => $request->armada_id,
            'created_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Jurnal buku besar untuk kas kecil keluar
        if ($request->jenis === 'keluar') {
            $bebanMap = [
                'bbm_armada'    => '5002',
                'gaji_karyawan' => '5003',
                'servis_armada' => '5004',
                'stnk_pajak'    => '5005',
                'kantor'        => '5006',
                'tabung'        => '5006',
                'lain_lain'     => '5007',
            ];
            $kodeBeban = $bebanMap[$request->kategori] ?? '5007';
            try {
                app(JurnalService::class)->kasKecil(
                    \Carbon\Carbon::parse($request->tanggal),
                    (int)$request->jumlah,
                    $kodeBeban,
                    $request->keterangan,
                    DB::getPdo()->lastInsertId()
                );
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning('[KasKecil] Gagal jurnal: '.$e->getMessage());
            }
        }

        return back()->with('success', 'Transaksi kas kecil berhasil disimpan.');
    }

    /** Hapus transaksi kas kecil */
    public function kasDestroy(int $id)
    {
        DB::table('kas_kecil')->where('id', $id)->delete();
        return back()->with('success', 'Transaksi berhasil dihapus.');
    }
}
