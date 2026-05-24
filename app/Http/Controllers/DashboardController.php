<?php

namespace App\Http\Controllers;

use App\Models\Agen;
use App\Models\SuratJalanHeader;
use App\Models\KitirHeader;
use App\Models\PangkalanToken;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $bulan = $request->get('bulan', now()->month);
        $tahun = $request->get('tahun', now()->year);
        $dari  = Carbon::create($tahun, $bulan, 1)->startOfMonth();
        $sampai= Carbon::create($tahun, $bulan, 1)->endOfMonth();

        // ── DISTRIBUSI ────────────────────────────────────────────
        // SJ bulan ini
        $sjBulanIni  = SuratJalanHeader::whereBetween('tanggal', [$dari, $sampai])->count();
        $sjSelesai   = SuratJalanHeader::whereBetween('tanggal', [$dari, $sampai])
                        ->where('status','selesai')->count();
        $sjAktif     = SuratJalanHeader::where('status','jalan')->count();

        // Total DO bulan ini
        $totalDO     = SuratJalanHeader::whereBetween('tanggal', [$dari, $sampai])
                        ->sum('qty_refil');

        // Total terkirim ke pangkalan
        $totalTerkirim = DB::table('surat_jalan_details as d')
            ->join('surat_jalan_headers as h', 'd.header_id', '=', 'h.id')
            ->whereBetween('h.tanggal', [$dari, $sampai])
            ->sum('d.qty_terima');

        // Realisasi hari ini
        $sjHariIni   = SuratJalanHeader::where('tanggal', today())
                        ->where('status','jalan')->count();

        // Kitir bulan ini
        $kitirBulanIni = KitirHeader::whereBetween('tanggal', [$dari, $sampai])->count();

        // ── KEUANGAN ──────────────────────────────────────────────
        // Tebusan bulan ini
        $tebusanBulan = DB::table('tebusan_headers')
            ->whereBetween('tanggal', [$dari, $sampai])
            ->sum('total_bayar');

        // BRImola saldo
        $brimolaIn  = DB::table('brimola_logs')->where('tipe','masuk')->sum('jumlah');
        $brimolaOut = DB::table('brimola_logs')->where('tipe','keluar')->sum('jumlah');
        $brimolaKum = $brimolaIn - $brimolaOut;

        // Piutang kerjasama outstanding
        $piutangOut = DB::table('piutang_kerjasama')
            ->where('status','belum_lunas')->sum('sisa_piutang');

        // Kas kecil saldo
        $kasIn  = DB::table('kas_kecil')->where('jenis','masuk')->sum('jumlah');
        $kasOut = DB::table('kas_kecil')->where('jenis','keluar')->sum('jumlah');
        $kasKecil = $kasIn - $kasOut;

        // Tebusan besok (perlu bayar)
        $tebusanBesok = DB::table('kitir_headers')
            ->where('tanggal', today()->addDay()->toDateString())
            ->sum('qty_do');

        // ── GUDANG & TABUNG ───────────────────────────────────────
        $bufferKosong = max(0, (int)(
            DB::table('gudang_tabung_kosong')->where('jenis','masuk')->sum('qty') -
            DB::table('gudang_tabung_kosong')->where('jenis','keluar')->sum('qty')
        ));
        $tabungIsi = max(0, (int)(
            DB::table('gudang_tabung_isi')->where('jenis','masuk')->sum('qty') -
            DB::table('gudang_tabung_isi')->where('jenis','keluar')->sum('qty')
        ));
        $tabungDiArmada = max(0, (int)(
            DB::table('armada_tabung')
                ->selectRaw("SUM(CASE WHEN jenis='masuk' THEN qty ELSE -qty END) as s")
                ->value('s') ?? 0
        ));
        $totalPinjaman = (int)DB::table('pinjaman_tabung')
            ->where('status','aktif')->sum('qty_aktif');
        $totalKepemilikan = $bufferKosong + $tabungIsi + $tabungDiArmada + $totalPinjaman;

        // ── NOTIFIKASI / ALERT ────────────────────────────────────
        $alerts = collect();

        // 1. Pinjaman tabung hampir kadaluarsa (30 hari)
        $pinjamanHampir = DB::table('pinjaman_tabung as pt')
            ->leftJoin('pangkalans as p','pt.pangkalan_id','=','p.id')
            ->where('pt.status','aktif')
            ->where('pt.tgl_berlaku_sampai','<=', now()->addDays(30)->toDateString())
            ->select('pt.*','p.nama_pangkalan')
            ->orderBy('pt.tgl_berlaku_sampai')
            ->get();
        foreach ($pinjamanHampir as $p) {
            $sisa = Carbon::parse($p->tgl_berlaku_sampai)->diffInDays(now());
            $alerts->push([
                'tipe'  => 'warning',
                'icon'  => '📄',
                'judul' => 'Perpanjang Perjanjian',
                'pesan' => "{$p->nama_pangkalan} — sisa {$sisa} hari (jatuh tempo ".
                           Carbon::parse($p->tgl_berlaku_sampai)->format('d/m/Y').")",
                'url'   => route('dashboard.agen.distribusi.gudang.index'),
            ]);
        }

        // 2. Uang kerjasama yang belum dibayar
        $kerjasamaBelum = DB::table('piutang_kerjasama as pk')
            ->leftJoin('pangkalans as p','pk.pangkalan_id','=','p.id')
            ->where('pk.status','belum_lunas')
            ->where('pk.jatuh_tempo','<=', now()->addDays(7)->toDateString())
            ->select('pk.*','p.nama_pangkalan')
            ->orderBy('pk.jatuh_tempo')
            ->limit(5)->get();
        foreach ($kerjasamaBelum as $k) {
            $alerts->push([
                'tipe'  => 'danger',
                'icon'  => '💳',
                'judul' => 'Uang Kerjasama Jatuh Tempo',
                'pesan' => "{$k->nama_pangkalan} — Rp ".number_format($k->sisa_piutang).
                           " (jatuh tempo ".Carbon::parse($k->jatuh_tempo)->format('d/m/Y').")",
                'url'   => route('dashboard.agen.akuntansi.piutang.index'),
            ]);
        }

        // 3. BRImola yang perlu diverifikasi
        $brimolaUnverif = DB::table('brimola_logs')
            ->where('status','pending')->count();
        if ($brimolaUnverif > 0) {
            $alerts->push([
                'tipe'  => 'info',
                'icon'  => '🏦',
                'judul' => 'BRImola Belum Diverifikasi',
                'pesan' => "{$brimolaUnverif} transaksi menunggu verifikasi",
                'url'   => route('dashboard.agen.akuntansi.brimola.index'),
            ]);
        }

        // 4. Kitir/DO besok
        if ($tebusanBesok > 0) {
            $hargaRefil = DB::table('referensi_harga')
                ->where('is_active',true)->value('harga_refil') ?? 0;
            $estimasiBayar = $tebusanBesok * $hargaRefil;
            $alerts->push([
                'tipe'  => 'info',
                'icon'  => '📦',
                'judul' => 'Tebusan DO Besok',
                'pesan' => "Estimasi ".number_format($tebusanBesok)." tabung — ".
                           "Rp ".number_format($estimasiBayar),
                'url'   => route('dashboard.agen.akuntansi.tebusan.index'),
            ]);
        }

        // 5. Buffer kosong rendah
        if ($bufferKosong < 100) {
            $alerts->push([
                'tipe'  => 'danger',
                'icon'  => '⚠️',
                'judul' => 'Buffer Tabung Kosong Rendah',
                'pesan' => "Hanya {$bufferKosong} tabung kosong di gudang (minimum 100)",
                'url'   => route('dashboard.agen.distribusi.gudang.index'),
            ]);
        }

        // ── GRAFIK distribusi 30 hari ─────────────────────────────
        $grafikData = collect();
        for ($i = 29; $i >= 0; $i--) {
            $tgl = now()->subDays($i)->toDateString();
            $grafikData->push([
                'tgl'      => Carbon::parse($tgl)->format('d/m'),
                'terkirim' => (int)DB::table('surat_jalan_details as d')
                    ->join('surat_jalan_headers as h','d.header_id','=','h.id')
                    ->where('h.tanggal', $tgl)->sum('d.qty_terima'),
            ]);
        }

        // ── GENDONGAN AKTIF ────────────────────────────────────────
        $gendongan = DB::table('stok_armada as sa')
            ->join('armadas as a','sa.armada_id','=','a.id')
            ->where('sa.status','jalan')
            ->where('sa.sisa_akhir','>',0)
            ->select('a.no_polisi','sa.sisa_akhir')
            ->get();

        $bulanList = collect(range(1,12))->mapWithKeys(fn($m) =>
            [$m => Carbon::create()->month($m)->translatedFormat('F')]
        );

        return view('dashboard.ikhtisar', compact(
            // Distribusi
            'sjBulanIni','sjSelesai','sjAktif','sjHariIni',
            'totalDO','totalTerkirim','kitirBulanIni','gendongan',
            // Keuangan
            'tebusanBulan','brimolaKum','piutangOut','kasKecil','tebusanBesok',
            // Gudang
            'bufferKosong','tabungIsi','tabungDiArmada','totalPinjaman','totalKepemilikan',
            // Alerts
            'alerts',
            // Grafik
            'grafikData',
            // Filter
            'bulan','tahun','bulanList'
        ));
    }
}
