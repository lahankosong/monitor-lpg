<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardIkhtisarController extends Controller
{
    public function index(Request $request)
    {
        $bulan  = (int)$request->get('bulan', now()->month);
        $tahun  = (int)$request->get('tahun', now()->year);
        $dari   = Carbon::create($tahun, $bulan, 1)->startOfMonth()->toDateString();
        $sampai = Carbon::create($tahun, $bulan, 1)->endOfMonth()->toDateString();

        // ── DISTRIBUSI ────────────────────────────────────────────
        $sjBulanIni  = 0; $sjSelesai = 0; $sjAktif = 0;
        $totalDO     = 0; $totalTerkirim = 0; $kitirBulanIni = 0;
        $sjHariIni   = 0; $gendongan = collect();

        try {
            $sjBulanIni  = DB::table('surat_jalan_headers')
                ->whereBetween('tanggal', [$dari, $sampai])->count();
            $sjSelesai   = DB::table('surat_jalan_headers')
                ->whereBetween('tanggal', [$dari, $sampai])
                ->where('status','selesai')->count();
            $sjAktif     = DB::table('surat_jalan_headers')
                ->where('status','jalan')->count();
            $totalDO     = (int)DB::table('surat_jalan_headers')
                ->whereBetween('tanggal', [$dari, $sampai])
                ->sum('qty_refil');
            $totalTerkirim = (int)DB::table('surat_jalan_details as d')
                ->join('surat_jalan_headers as h','d.header_id','=','h.id')
                ->whereBetween('h.tanggal', [$dari, $sampai])
                ->sum('d.qty_terima');
            $sjHariIni   = DB::table('surat_jalan_headers')
                ->where('tanggal', today()->toDateString())
                ->where('status','jalan')->count();
        } catch (\Exception $e) {}

        // Kitir — tabel: kitirs, kolom tanggal: valid_from
        try {
            $kitirBulanIni = DB::table('kitirs')
                ->whereBetween('valid_from', [$dari, $sampai])->count();
        } catch (\Exception $e) {}

        // Gendongan aktif di armada
        try {
            $gendongan = DB::table('stok_armada as sa')
                ->join('armadas as a','sa.armada_id','=','a.id')
                ->where('sa.status','jalan')
                ->where('sa.sisa_akhir','>',0)
                ->select('a.no_polisi','sa.sisa_akhir')
                ->get();
        } catch (\Exception $e) {}

        // ── KEUANGAN ──────────────────────────────────────────────
        $tebusanBulan = 0; $tebusanBesok = 0;

        // Tebusan — tabel: tebusan_kitirs, kolom: tanggal_bayar, total_bayar
        try {
            $tebusanBulan = (int)DB::table('tebusan_kitirs')
                ->whereBetween('tanggal_bayar', [$dari, $sampai])
                ->sum('total_bayar');
            // Estimasi tebusan besok dari kitir yang valid besok
            $tebusanBesok = (int)DB::table('kitirs')
                ->where('valid_from', today()->addDay()->toDateString())
                ->where('status','aktif')
                ->sum('total_kuota');
        } catch (\Exception $e) {}

        // BRImola — tabel: brimola_transaksi, kolom: total_bayar, status
        $brimolaKum    = 0;
        $brimolaUnverif= 0;
        try {
            $brimolaKum     = (int)DB::table('brimola_transaksi')
                ->where('status','verified')
                ->sum('total_bayar');
            $brimolaUnverif = DB::table('brimola_transaksi')
                ->where('status','unmatched')->count();
        } catch (\Exception $e) {}

        // Piutang kerjasama — kolom: status enum(belum_bayar,sebagian,lunas), sisa_tagihan
        $piutangOut = 0;
        try {
            $piutangOut = (int)DB::table('piutang_kerjasama')
                ->whereIn('status',['belum_bayar','sebagian'])
                ->sum('sisa_tagihan');
        } catch (\Exception $e) {}

        // Kas kecil — kolom: tanggal, jenis enum(masuk,keluar), jumlah
        $kasKecil = 0;
        try {
            $kasKecil = (int)(
                DB::table('kas_kecil')->where('jenis','masuk')->sum('jumlah') -
                DB::table('kas_kecil')->where('jenis','keluar')->sum('jumlah')
            );
        } catch (\Exception $e) {}

        // ── GUDANG & TABUNG ───────────────────────────────────────
        // gudang_tabung_kosong & gudang_tabung_isi: kolom jenis(masuk/keluar), qty
        $bufferKosong   = 0; $tabungIsi = 0;
        $tabungDiArmada = 0; $totalPinjaman = 0;

        try {
            $bufferKosong = max(0, (int)(
                DB::table('gudang_tabung_kosong')->where('jenis','masuk')->sum('qty') -
                DB::table('gudang_tabung_kosong')->where('jenis','keluar')->sum('qty')
            ));
            $tabungIsi = max(0, (int)(
                DB::table('gudang_tabung_isi')->where('jenis','masuk')->sum('qty') -
                DB::table('gudang_tabung_isi')->where('jenis','keluar')->sum('qty')
            ));
        } catch (\Exception $e) {}

        // armada_tabung: kolom qty, jenis(masuk/keluar)
        try {
            $tabungDiArmada = max(0, (int)(
                DB::table('armada_tabung')
                    ->selectRaw("SUM(CASE WHEN jenis='masuk' THEN qty ELSE -qty END) as s")
                    ->value('s') ?? 0
            ));
        } catch (\Exception $e) {}

        // pinjaman_tabung: kolom qty_aktif, status(aktif/lunas/kadaluarsa)
        try {
            $totalPinjaman = (int)DB::table('pinjaman_tabung')
                ->where('status','aktif')->sum('qty_aktif');
        } catch (\Exception $e) {}

        $totalKepemilikan = $bufferKosong + $tabungIsi + $tabungDiArmada + $totalPinjaman;

        // ── NOTIFIKASI / ALERT ────────────────────────────────────
        $alerts = collect();

        try {
            // 1. Pinjaman tabung hampir kadaluarsa (30 hari ke depan)
            // pinjaman_tabung: tgl_berlaku_sampai, status aktif
            $pinjamanHampir = DB::table('pinjaman_tabung as pt')
                ->leftJoin('pangkalans as p','pt.pangkalan_id','=','p.id')
                ->where('pt.status','aktif')
                ->where('pt.tgl_berlaku_sampai','<=', now()->addDays(30)->toDateString())
                ->orderBy('pt.tgl_berlaku_sampai')
                ->select('pt.tgl_berlaku_sampai','p.nama_pangkalan')
                ->get();
            foreach ($pinjamanHampir as $p) {
                $sisa = now()->diffInDays(Carbon::parse($p->tgl_berlaku_sampai));
                $alerts->push([
                    'tipe'  => $sisa <= 7 ? 'danger' : 'warning',
                    'icon'  => '📄',
                    'judul' => 'Perpanjang Perjanjian Tabung',
                    'pesan' => ($p->nama_pangkalan ?? 'Pangkalan') .
                               " — sisa {$sisa} hari (" .
                               Carbon::parse($p->tgl_berlaku_sampai)->format('d/m/Y') . ")",
                    'url'   => route('dashboard.agen.distribusi.gudang.index'),
                ]);
            }
        } catch (\Exception $e) {}

        try {
            // 2. Piutang kerjasama jatuh tempo 7 hari ke depan
            $kerjasamaBelum = DB::table('piutang_kerjasama as pk')
                ->leftJoin('pangkalans as p','pk.pangkalan_id','=','p.id')
                ->whereIn('pk.status',['belum_bayar','sebagian'])
                ->where('pk.jatuh_tempo','<=', now()->addDays(7)->toDateString())
                ->orderBy('pk.jatuh_tempo')
                ->select('pk.sisa_tagihan','pk.jatuh_tempo','p.nama_pangkalan')
                ->limit(5)->get();
            foreach ($kerjasamaBelum as $k) {
                $alerts->push([
                    'tipe'  => 'danger',
                    'icon'  => '💳',
                    'judul' => 'Piutang Kerjasama Jatuh Tempo',
                    'pesan' => ($k->nama_pangkalan ?? '?') .
                               " — Rp " . number_format($k->sisa_tagihan) .
                               " (" . Carbon::parse($k->jatuh_tempo)->format('d/m/Y') . ")",
                    'url'   => route('dashboard.agen.akuntansi.piutang.index'),
                ]);
            }
        } catch (\Exception $e) {}

        // 3. BRImola unmatched/unverified
        if ($brimolaUnverif > 0) {
            $alerts->push([
                'tipe'  => 'info',
                'icon'  => '🏦',
                'judul' => 'BRImola Belum Diverifikasi',
                'pesan' => "{$brimolaUnverif} transaksi menunggu verifikasi/pencocokan",
                'url'   => route('dashboard.agen.akuntansi.brimola.index'),
            ]);
        }

        // 4. DO besok
        if ($tebusanBesok > 0) {
            $alerts->push([
                'tipe'  => 'info',
                'icon'  => '📦',
                'judul' => 'Kitir Aktif Besok',
                'pesan' => "Estimasi {$tebusanBesok} tabung perlu ditebus besok",
                'url'   => route('dashboard.agen.akuntansi.tebusan.index'),
            ]);
        }

        // 5. Buffer kosong rendah
        if ($bufferKosong < 100 && $totalKepemilikan > 0) {
            $alerts->push([
                'tipe'  => 'danger',
                'icon'  => '⚠️',
                'judul' => 'Buffer Tabung Kosong Rendah',
                'pesan' => "Hanya {$bufferKosong} tabung kosong di gudang",
                'url'   => route('dashboard.agen.distribusi.gudang.index'),
            ]);
        }

        // ── GRAFIK distribusi 30 hari ─────────────────────────────
        $grafikData = collect();
        try {
            for ($i = 29; $i >= 0; $i--) {
                $tgl = now()->subDays($i)->toDateString();
                $grafikData->push([
                    'tgl'      => Carbon::parse($tgl)->format('d/m'),
                    'terkirim' => (int)DB::table('surat_jalan_details as d')
                        ->join('surat_jalan_headers as h','d.header_id','=','h.id')
                        ->where('h.tanggal', $tgl)->sum('d.qty_terima'),
                ]);
            }
        } catch (\Exception $e) {
            for ($i = 29; $i >= 0; $i--) {
                $grafikData->push(['tgl' => now()->subDays($i)->format('d/m'), 'terkirim' => 0]);
            }
        }

        $bulanList = collect(range(1,12))->mapWithKeys(fn($m) =>
            [$m => Carbon::create()->month($m)->translatedFormat('F')]
        );

        return view('dashboard.ikhtisar', compact(
            'sjBulanIni','sjSelesai','sjAktif','sjHariIni',
            'totalDO','totalTerkirim','kitirBulanIni','gendongan',
            'tebusanBulan','brimolaKum','piutangOut','kasKecil','tebusanBesok',
            'bufferKosong','tabungIsi','tabungDiArmada','totalPinjaman','totalKepemilikan',
            'alerts','grafikData','bulan','tahun','bulanList'
        ));
    }
}