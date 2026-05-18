<?php
namespace App\Http\Controllers\Agen;

use App\Http\Controllers\Controller;
use App\Services\JurnalService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BukuBesarController extends Controller
{
    public function __construct(private JurnalService $jurnal) {}

    // ─────────────────────────────────────────────────────────────
    // BUKU BESAR per akun
    // ─────────────────────────────────────────────────────────────
    public function index(Request $request)
    {
        $bulan    = (int)$request->get('bulan', now()->month);
        $tahun    = (int)$request->get('tahun', now()->year);
        $kodeAkun = $request->get('akun', '');

        $akuns    = DB::table('akun_keuangan')->where('is_aktif', true)->orderBy('kode')->get();
        $bulanList= collect(range(1,12))->mapWithKeys(fn($m) =>
            [$m => Carbon::create()->month($m)->translatedFormat('F')]
        );

        $bukuBesar = $this->loadBukuBesar($bulan, $tahun, $kodeAkun ?: null);

        return view('agen.akuntansi.buku-besar', compact(
            'akuns','bukuBesar','bulan','tahun','kodeAkun','bulanList'
        ));
    }

    /** Load semua akun atau satu akun, return grouped by kelompok */
    private function loadBukuBesar(int $bulan, int $tahun, ?string $filterKode = null): array
    {
        $dariTgl = Carbon::create($tahun, $bulan, 1);
        $sampai  = $dariTgl->copy()->endOfMonth();

        $akuns = DB::table('akun_keuangan')
            ->where('is_aktif', true)
            ->when($filterKode, fn($q) => $q->where('kode', $filterKode))
            ->orderBy('kode')->get();

        $result = [];
        foreach ($akuns as $akun) {
            $saldoAwal = $this->jurnal->saldoAkun($akun->kode, $dariTgl->copy()->subDay());

            $mutasi = DB::table('jurnal_details as jd')
                ->join('jurnal_headers as jh', 'jd.jurnal_id', '=', 'jh.id')
                ->where('jd.kode_akun', $akun->kode)
                ->whereBetween('jh.tanggal', [$dariTgl->toDateString(), $sampai->toDateString()])
                ->select(
                    'jh.tanggal','jh.no_jurnal',
                    'jh.keterangan as ket_header','jh.modul','jh.is_otomatis',
                    'jd.posisi','jd.jumlah','jd.keterangan as ket_detail'
                )
                ->orderBy('jh.tanggal')->orderBy('jh.id')
                ->get();

            // Saldo berjalan
            $saldo = $saldoAwal;
            $mutasi = $mutasi->map(function ($m) use (&$saldo, $akun) {
                $saldo += $akun->posisi_normal === 'debit'
                    ? ($m->posisi === 'debit' ? $m->jumlah : -$m->jumlah)
                    : ($m->posisi === 'kredit' ? $m->jumlah : -$m->jumlah);
                $m->saldo_running = $saldo;
                return $m;
            });

            $result[$akun->kelompok][] = [
                'akun'      => $akun,
                'mutasi'    => $mutasi,
                'saldo_awal'=> $saldoAwal,
            ];
        }

        // Urutan kelompok
        $urutan = ['aset','kewajiban','modal','pendapatan','beban'];
        uksort($result, fn($a,$b) =>
            array_search($a, $urutan) <=> array_search($b, $urutan)
        );

        return $result;
    }

    // ─────────────────────────────────────────────────────────────
    // LABA RUGI
    // ─────────────────────────────────────────────────────────────
    public function labaRugi(Request $request)
    {
        $bulan = $request->get('bulan', now()->month);
        $tahun = $request->get('tahun', now()->year);
        $dari  = Carbon::create($tahun, $bulan, 1);
        $sampai= $dari->copy()->endOfMonth();

        // Pendapatan
        $pendapatan = DB::table('akun_keuangan')
            ->where('kelompok', 'pendapatan')
            ->orderBy('kode')->get()
            ->map(fn($a) => (object)[
                'kode'  => $a->kode,
                'nama'  => $a->nama,
                'nilai' => $this->nilaiPeriode($a->kode, $dari, $sampai, 'kredit'),
            ]);

        // Beban
        $beban = DB::table('akun_keuangan')
            ->where('kelompok', 'beban')
            ->orderBy('kode')->get()
            ->map(fn($a) => (object)[
                'kode'  => $a->kode,
                'nama'  => $a->nama,
                'nilai' => $this->nilaiPeriode($a->kode, $dari, $sampai, 'debit'),
            ]);

        $totalPendapatan = $pendapatan->sum('nilai');
        $totalBeban      = $beban->sum('nilai');
        $labaKotor       = $totalPendapatan - $this->nilaiPeriode('5001', $dari, $sampai, 'debit');
        $labaBersih      = $totalPendapatan - $totalBeban;

        $bulanList = collect(range(1,12))->mapWithKeys(fn($m) =>
            [$m => Carbon::create()->month($m)->translatedFormat('F')]
        );

        return view('agen.akuntansi.laba-rugi', compact(
            'pendapatan','beban','totalPendapatan','totalBeban',
            'labaKotor','labaBersih','bulan','tahun','bulanList'
        ));
    }

    // ─────────────────────────────────────────────────────────────
    // NERACA
    // ─────────────────────────────────────────────────────────────
    public function neraca(Request $request)
    {
        $tanggal = $request->get('tanggal', now()->toDateString());
        $tgl     = Carbon::parse($tanggal);

        $kelompok = ['aset','kewajiban','modal'];
        $data     = [];

        foreach ($kelompok as $k) {
            $akuns = DB::table('akun_keuangan')
                ->where('kelompok', $k)
                ->where('is_aktif', true)
                ->orderBy('kode')->get();

            $data[$k] = $akuns->map(fn($a) => (object)[
                'kode'  => $a->kode,
                'nama'  => $a->nama,
                'saldo' => $this->jurnal->saldoAkun($a->kode, $tgl),
            ]);
        }

        // Laba berjalan masuk ke modal
        $labaYtd = $this->labaSampai($tgl);
        $totalAset       = $data['aset']->sum('saldo');
        $totalKewajiban  = $data['kewajiban']->sum('saldo');
        $totalModal      = $data['modal']->sum('saldo') + $labaYtd;
        $balance         = $totalAset - ($totalKewajiban + $totalModal);

        return view('agen.akuntansi.neraca', compact(
            'data','totalAset','totalKewajiban','totalModal',
            'labaYtd','balance','tanggal'
        ));
    }

    // ─────────────────────────────────────────────────────────────
    // JURNAL — input manual & daftar
    // ─────────────────────────────────────────────────────────────
    public function jurnalIndex(Request $request)
    {
        $bulan = $request->get('bulan', now()->month);
        $tahun = $request->get('tahun', now()->year);
        $modul = $request->get('modul', '');

        $jurnals = DB::table('jurnal_headers as jh')
            ->leftJoin('users as u', 'jh.dibuat_oleh', '=', 'u.id')
            ->whereYear('jh.tanggal', $tahun)
            ->whereMonth('jh.tanggal', $bulan)
            ->when($modul, fn($q) => $q->where('jh.modul', $modul))
            ->select('jh.*', 'u.name as nama_user')
            ->orderByDesc('jh.tanggal')->orderByDesc('jh.id')
            ->paginate(30)->withQueryString();

        // Attach details ke setiap jurnal
        $jurnalIds = $jurnals->pluck('id');
        $details   = DB::table('jurnal_details as jd')
            ->join('akun_keuangan as ak', 'jd.kode_akun', '=', 'ak.kode')
            ->whereIn('jd.jurnal_id', $jurnalIds)
            ->select('jd.*','ak.nama as nama_akun')
            ->orderBy('jd.posisi') // debit dulu
            ->get()->groupBy('jurnal_id');

        $akuns     = DB::table('akun_keuangan')->where('is_aktif',true)->orderBy('kode')->get();
        $bulanList = collect(range(1,12))->mapWithKeys(fn($m) =>
            [$m => Carbon::create()->month($m)->translatedFormat('F')]
        );

        return view('agen.akuntansi.jurnal', compact(
            'jurnals','details','akuns','bulan','tahun','bulanList','modul'
        ));
    }

    /** Simpan jurnal manual */
    public function jurnalStore(Request $request)
    {
        $request->validate([
            'tanggal'              => 'required|date',
            'keterangan'           => 'required|string|max:255',
            'modul'                => 'required|string',
            'akun.*'               => 'required|exists:akun_keuangan,kode',
            'posisi.*'             => 'required|in:debit,kredit',
            'jumlah.*'             => 'required|integer|min:1',
        ]);

        $details = [];
        foreach ($request->akun as $i => $kode) {
            $details[] = [
                'kode_akun'  => $kode,
                'posisi'     => $request->posisi[$i],
                'jumlah'     => (int)$request->jumlah[$i],
                'keterangan' => $request->ket_detail[$i] ?? null,
            ];
        }

        try {
            $this->jurnal->buat([
                'tanggal'    => $request->tanggal,
                'keterangan' => $request->keterangan,
                'modul'      => $request->modul,
                'referensi'  => $request->referensi,
                'is_otomatis'=> false,
            ], $details);
        } catch (\Exception $e) {
            return back()->withErrors(['msg' => $e->getMessage()]);
        }

        return back()->with('success', 'Jurnal berhasil disimpan.');
    }

    /** Modal/Prive — shortcut untuk transaksi owner */
    public function modalStore(Request $request)
    {
        $request->validate([
            'tanggal'   => 'required|date',
            'tipe'      => 'required|in:modal_disetor,pinjaman_pemilik,prive,bayar_utang_pemilik',
            'jumlah'    => 'required|integer|min:1',
            'akun_kas'  => 'required|in:1001,1002',
            'keterangan'=> 'nullable|string|max:255',
        ]);

        $tgl    = Carbon::parse($request->tanggal);
        $jumlah = (int)$request->jumlah;
        $ket    = $request->keterangan;

        try {
            match($request->tipe) {
                'modal_disetor','pinjaman_pemilik' =>
                    $this->jurnal->modalMasuk($tgl, $jumlah, $request->akun_kas, $request->tipe, $ket),
                'prive' =>
                    $this->jurnal->prive($tgl, $jumlah, $request->akun_kas, $ket),
                'bayar_utang_pemilik' =>
                    $this->jurnal->buat([
                        'tanggal'    => $tgl->toDateString(),
                        'modul'      => 'utang_pemilik',
                        'keterangan' => $ket ?: 'Bayar kembali pinjaman pemilik',
                        'is_otomatis'=> false,
                    ], [
                        ['kode_akun' => '2003',            'posisi' => 'debit',  'jumlah' => $jumlah],
                        ['kode_akun' => $request->akun_kas,'posisi' => 'kredit', 'jumlah' => $jumlah],
                    ]),
            };
        } catch (\Exception $e) {
            return back()->withErrors(['msg' => $e->getMessage()]);
        }

        return back()->with('success', 'Transaksi modal berhasil dicatat.');
    }

    // ─────────────────────────────────────────────────────────────
    // EXPORT
    // ─────────────────────────────────────────────────────────────

    public function exportPdf(Request $request)
    {
        $bulan    = (int)$request->get('bulan', now()->month);
        $tahun    = (int)$request->get('tahun', now()->year);
        $kodeAkun = $request->get('akun', '');

        $bukuBesar = $this->loadBukuBesar($bulan, $tahun, $kodeAkun ?: null);
        $agen      = \App\Models\Agen::first();
        $bulanList = collect(range(1,12))->mapWithKeys(fn($m) =>
            [$m => Carbon::create()->month($m)->translatedFormat('F')]
        );

        $html = view('agen.akuntansi.buku-besar-print', compact(
            'bukuBesar','agen','bulan','tahun','bulanList','kodeAkun'
        ))->render();

        return response($html)->header('Content-Type','text/html');
        // Untuk PDF pakai dompdf jika tersedia:
        // $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->setPaper('a4','landscape');
        // return $pdf->download("buku-besar-{$tahun}-{$bulan}.pdf");
    }

    public function exportExcel(Request $request)
    {
        $bulan    = (int)$request->get('bulan', now()->month);
        $tahun    = (int)$request->get('tahun', now()->year);
        $kodeAkun = $request->get('akun', '');

        $bukuBesar = $this->loadBukuBesar($bulan, $tahun, $kodeAkun ?: null);
        $agen      = \App\Models\Agen::first();
        $bulanList = collect(range(1,12))->mapWithKeys(fn($m) =>
            [$m => Carbon::create()->month($m)->translatedFormat('F')]
        );
        $bulanNama = $bulanList[$bulan];

        $filename = "buku-besar-{$tahun}-{$bulan}.csv";

        $callback = function () use ($bukuBesar, $agen, $bulanNama, $tahun) {
            $h = fopen('php://output','w');
            fprintf($h, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8

            // Kop
            fputcsv($h, [$agen?->nama_agen ?? 'AGEN LPG SUBSIDI']);
            fputcsv($h, ["Kode: ".($agen?->kode_agen ?? '')." | Alamat: ".($agen?->alamat_agen ?? '')]);
            fputcsv($h, ["BUKU BESAR PERIODE: {$bulanNama} {$tahun}"]);
            fputcsv($h, []);

            $kelompokLabel = [
                'aset'=>'ASET','kewajiban'=>'KEWAJIBAN','modal'=>'MODAL',
                'pendapatan'=>'PENDAPATAN','beban'=>'BEBAN',
            ];

            foreach ($bukuBesar as $kel => $akunList) {
                fputcsv($h, [$kelompokLabel[$kel] ?? strtoupper($kel)]);

                foreach ($akunList as $akunData) {
                    $akun   = $akunData['akun'];
                    $mutasi = $akunData['mutasi'];
                    if ($mutasi->isEmpty() && $akunData['saldo_awal'] == 0) continue;

                    fputcsv($h, ["{$akun->kode} - {$akun->nama}"]);
                    fputcsv($h, ['Tanggal','No Jurnal','Keterangan','Modul','Debit','Kredit','Saldo']);
                    fputcsv($h, ['Saldo Awal','','','','','',number_format($akunData['saldo_awal'],0,',','.')]);

                    foreach ($mutasi as $m) {
                        fputcsv($h, [
                            \Carbon\Carbon::parse($m->tanggal)->format('d/m/Y'),
                            $m->no_jurnal,
                            $m->ket_detail ?: $m->ket_header,
                            strtoupper(str_replace('_',' ',$m->modul)),
                            $m->posisi==='debit'  ? number_format($m->jumlah,0,',','.') : '',
                            $m->posisi==='kredit' ? number_format($m->jumlah,0,',','.') : '',
                            number_format($m->saldo_running,0,',','.'),
                        ]);
                    }
                    fputcsv($h, []);
                }
            }
            fclose($h);
        };

        return response()->stream($callback, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────
    private function nilaiPeriode(string $kode, Carbon $dari, Carbon $sampai, string $posisi): int
    {
        return (int)DB::table('jurnal_details as jd')
            ->join('jurnal_headers as jh', 'jd.jurnal_id', '=', 'jh.id')
            ->where('jd.kode_akun', $kode)
            ->where('jd.posisi', $posisi)
            ->whereBetween('jh.tanggal', [$dari->toDateString(), $sampai->toDateString()])
            ->sum('jd.jumlah');
    }

    private function labaSampai(Carbon $tgl): int
    {
        $pendapatan = DB::table('jurnal_details as jd')
            ->join('jurnal_headers as jh', 'jd.jurnal_id', '=', 'jh.id')
            ->join('akun_keuangan as ak', 'jd.kode_akun', '=', 'ak.kode')
            ->where('ak.kelompok', 'pendapatan')
            ->where('jd.posisi', 'kredit')
            ->where('jh.tanggal', '<=', $tgl->toDateString())
            ->sum('jd.jumlah');

        $beban = DB::table('jurnal_details as jd')
            ->join('jurnal_headers as jh', 'jd.jurnal_id', '=', 'jh.id')
            ->join('akun_keuangan as ak', 'jd.kode_akun', '=', 'ak.kode')
            ->where('ak.kelompok', 'beban')
            ->where('jd.posisi', 'debit')
            ->where('jh.tanggal', '<=', $tgl->toDateString())
            ->sum('jd.jumlah');

        return (int)($pendapatan - $beban);
    }
}
