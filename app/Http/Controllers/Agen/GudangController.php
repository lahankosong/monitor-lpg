<?php
namespace App\Http\Controllers\Agen;

use App\Http\Controllers\Controller;
use App\Services\NotifikasiService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GudangController extends Controller
{
    const ALERT_KOSONG_MIN = 100; // alert jika buffer kosong < 100

    // ─────────────────────────────────────────────────────────────
    // DASHBOARD GUDANG
    // ─────────────────────────────────────────────────────────────
    public function index(Request $request)
    {
        $bulan = $request->get('bulan', now()->month);
        $tahun = $request->get('tahun', now()->year);

        // ── Hitung saldo tabung kosong (buffer) ──────────────────
        $masukKosong  = DB::table('gudang_tabung_kosong')->where('jenis','masuk')->sum('qty');
        $keluarKosong = DB::table('gudang_tabung_kosong')->where('jenis','keluar')->sum('qty');
        $saldoKosong  = (int)($masukKosong - $keluarKosong);

        // ── Hitung saldo tabung isi ───────────────────────────────
        $masukIsi  = DB::table('gudang_tabung_isi')->where('jenis','masuk')->sum('qty');
        $keluarIsi = DB::table('gudang_tabung_isi')->where('jenis','keluar')->sum('qty');
        $saldoIsi  = (int)($masukIsi - $keluarIsi);

        // ── Tabung kosong di armada (alokasi permanen) ────────────
        // Sumber kebenaran: armada_tabung (alokasi permanen per armada)
        // = total masuk ke armada - total yang dikembalikan ke gudang
        $kosongDiArmada = (int) DB::table('armada_tabung')
            ->selectRaw("SUM(CASE WHEN jenis='masuk' THEN qty ELSE -qty END) as saldo")
            ->value('saldo');
        $kosongDiArmada = max(0, $kosongDiArmada ?? 0);

        // ── Alokasi tabung per armada (permanen) ─────────────────
        $alokasiArmada = DB::table('armada_tabung as at')
            ->join('armadas as a', 'at.armada_id', '=', 'a.id')
            ->where('a.is_active', true)
            ->select(
                'at.armada_id', 'a.no_polisi', 'a.kapasitas_tabung',
                'a.layak_sampai', 'a.tahun_pembuatan',
                DB::raw("SUM(CASE WHEN at.jenis='masuk' THEN at.qty ELSE 0 END) as total_masuk"),
                DB::raw("SUM(CASE WHEN at.jenis='keluar' THEN at.qty ELSE 0 END) as total_keluar"),
                DB::raw("SUM(CASE WHEN at.jenis='masuk' THEN at.qty ELSE -at.qty END) as saldo_tabung")
            )
            ->groupBy('at.armada_id','a.no_polisi','a.kapasitas_tabung','a.layak_sampai','a.tahun_pembuatan')
            ->orderBy('a.no_polisi')
            ->get();

        // Armada aktif yang belum pernah dapat alokasi tabung
        $armadaBelumAlokasi = DB::table('armadas as a')
            ->leftJoin('armada_tabung as at', 'a.id', '=', 'at.armada_id')
            ->where('a.is_active', true)
            ->whereNull('at.id')
            ->select('a.id','a.no_polisi','a.kapasitas_tabung','a.layak_sampai','a.tahun_pembuatan')
            ->get();

        // ── Kepemilikan tabung agen ───────────────────────────────
        $totalPinjaman = DB::table('pinjaman_tabung')
            ->where('status','aktif')->sum('qty_aktif');
        $totalKepemilikan = $saldoKosong + $saldoIsi + (int)$totalPinjaman + $kosongDiArmada;

        // Rincian pinjaman per pihak
        $pinjamanAktif = DB::table('pinjaman_tabung as pt')
            ->leftJoin('pangkalans as p', 'pt.pangkalan_id', '=', 'p.id')
            ->where('pt.status', 'aktif')
            ->select('pt.*', 'p.nama_pangkalan', 'p.no_reg')
            ->orderBy('pt.tgl_berlaku_sampai')
            ->get();

        // ── Stok armada aktif ─────────────────────────────────────
        $stokArmada = DB::table('stok_armada as sa')
            ->join('armadas as a', 'sa.armada_id', '=', 'a.id')
            ->join('surat_jalan_headers as sj', 'sa.sj_header_id', '=', 'sj.id')
            ->where('sa.status', 'jalan')
            ->select('sa.*', 'a.no_polisi', 'sj.no_sj', 'sj.tanggal')
            ->get();

        // ── Mutasi bulan ini (kosong + isi digabung) ─────────────
        $mutasiKosong = DB::table('gudang_tabung_kosong')
            ->whereYear('tanggal', $tahun)->whereMonth('tanggal', $bulan)
            ->select('*', DB::raw('"kosong" as tipe_tabung'))->get();

        $mutasiIsi = DB::table('gudang_tabung_isi')
            ->whereYear('tanggal', $tahun)->whereMonth('tanggal', $bulan)
            ->select('*', DB::raw('"isi" as tipe_tabung'))->get();

        $mutasi = $mutasiKosong->concat($mutasiIsi)
            ->sortByDesc('tanggal')->sortByDesc('id')
            ->values();

        // ── Pinjaman hampir kadaluarsa (30 hari) ─────────────────
        $hampirKadaluarsa = DB::table('pinjaman_tabung as pt')
            ->leftJoin('pangkalans as p', 'pt.pangkalan_id', '=', 'p.id')
            ->where('pt.status', 'aktif')
            ->where('pt.tgl_berlaku_sampai', '<=', now()->addDays(30)->toDateString())
            ->select('pt.*', 'p.nama_pangkalan')
            ->orderBy('pt.tgl_berlaku_sampai')
            ->get();

        $bulanList = collect(range(1,12))->mapWithKeys(fn($m) =>
            [$m => Carbon::create()->month($m)->translatedFormat('F')]
        );

        // Cek alert stok rendah
        if ($saldoKosong < self::ALERT_KOSONG_MIN) {
            NotifikasiService::keDirektur(
                'stok_gudang_rendah',
                'Stok Tabung Kosong Rendah',
                "Buffer gudang hanya {$saldoKosong} tabung kosong (minimum ".self::ALERT_KOSONG_MIN.")",
                route('dashboard.agen.distribusi.gudang.index')
            );
        }

        return view('agen.distribusi.gudang', compact(
            'saldoKosong','saldoIsi','totalKepemilikan','totalPinjaman',
            'kosongDiArmada','pinjamanAktif','stokArmada','mutasi','hampirKadaluarsa',
            'alokasiArmada','armadaBelumAlokasi',
            'bulan','tahun','bulanList'
        ));
    }

    // ─────────────────────────────────────────────────────────────
    // TAMBAH STOK KOSONG — beli dari Pertamina
    // ─────────────────────────────────────────────────────────────
    public function beliTabung(Request $request)
    {
        $request->validate([
            'tgl_beli'         => 'required|date',
            'qty'              => 'required|integer|min:1',
            'harga_per_tabung' => 'nullable|integer|min:0',
            'no_faktur'        => 'nullable|string|max:50',
            'keterangan'       => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($request) {
            // Catat aset
            DB::table('tabung_aset')->insert([
                'tgl_beli'         => $request->tgl_beli,
                'qty'              => $request->qty,
                'harga_per_tabung' => $request->harga_per_tabung ?? 0,
                'no_faktur'        => $request->no_faktur,
                'keterangan'       => $request->keterangan,
                'created_by'       => auth()->id(),
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);

            // Masuk ke buffer gudang
            DB::table('gudang_tabung_kosong')->insert([
                'jenis'       => 'masuk',
                'sumber'      => 'beli_pertamina',
                'qty'         => $request->qty,
                'tanggal'     => $request->tgl_beli,
                'no_referensi'=> $request->no_faktur,
                'keterangan'  => "Beli tabung baru dari Pertamina. ".($request->keterangan ?? ''),
                'created_by'  => auth()->id(),
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        });

        return back()->with('success',
            "Pembelian {$request->qty} tabung kosong berhasil dicatat."
        );
    }

    // ─────────────────────────────────────────────────────────────
    // CATAT PENGELUARAN TABUNG KOSONG ke Armada
    // ─────────────────────────────────────────────────────────────
    public function keluarKosong(Request $request)
    {
        $request->validate([
            'tanggal'   => 'required|date',
            'qty'       => 'required|integer|min:1',
            'tujuan'    => 'required|in:ke_armada,pinjam_pangkalan,pinjam_cabang,penyesuaian',
            'armada_id' => 'nullable|exists:armadas,id',
            'keterangan'=> 'required|string|max:255',
        ]);

        $saldo = (int)(DB::table('gudang_tabung_kosong')->where('jenis','masuk')->sum('qty')
               - DB::table('gudang_tabung_kosong')->where('jenis','keluar')->sum('qty'));

        if ($saldo < $request->qty) {
            return back()->withErrors([
                'qty' => "Stok kosong tidak cukup. Tersedia: {$saldo} tabung."
            ]);
        }

        DB::table('gudang_tabung_kosong')->insert([
            'jenis'      => 'keluar',
            'tujuan'     => $request->tujuan,
            'sumber'     => null,
            'qty'        => $request->qty,
            'tanggal'    => $request->tanggal,
            'armada_id'  => $request->armada_id,
            'keterangan' => $request->keterangan,
            'created_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success',
            "{$request->qty} tabung kosong berhasil dicatat keluar."
        );
    }

    // ─────────────────────────────────────────────────────────────
    // CATAT TABUNG ISI TURUN ke Gudang (gendongan dari armada)
    // ─────────────────────────────────────────────────────────────
    public function masukIsi(Request $request)
    {
        $request->validate([
            'tanggal'   => 'required|date',
            'qty'       => 'required|integer|min:1',
            'armada_id' => 'nullable|exists:armadas,id',
            'keterangan'=> 'nullable|string|max:255',
        ]);

        DB::table('gudang_tabung_isi')->insert([
            'jenis'      => 'masuk',
            'sumber'     => 'turun_armada',
            'qty'        => $request->qty,
            'tanggal'    => $request->tanggal,
            'armada_id'  => $request->armada_id,
            'keterangan' => $request->keterangan ?? 'Tabung isi turun dari armada ke gudang',
            'created_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success',
            "{$request->qty} tabung isi berhasil dicatat masuk ke gudang."
        );
    }

    // ─────────────────────────────────────────────────────────────
    // CATAT PINJAMAN TABUNG ke Pangkalan / Cabang
    // ─────────────────────────────────────────────────────────────
    public function pinjamStore(Request $request)
    {
        $request->validate([
            'pangkalan_id'       => 'required|exists:pangkalans,id',
            'pihak'              => 'required|in:pangkalan,cabang',
            'nama_pihak'         => 'nullable|string|max:100',
            'qty_pinjam'         => 'required|integer|min:1',
            'tgl_pinjam'         => 'required|date',
            'tgl_berlaku_sampai' => 'required|date|after:tgl_pinjam',
            'no_perjanjian'      => 'nullable|string|max:50',
            'keterangan'         => 'nullable|string|max:255',
        ]);

        // Cek saldo buffer
        $saldo = (int)(DB::table('gudang_tabung_kosong')->where('jenis','masuk')->sum('qty')
               - DB::table('gudang_tabung_kosong')->where('jenis','keluar')->sum('qty'));

        if ($saldo < $request->qty_pinjam) {
            return back()->withErrors([
                'qty_pinjam' => "Buffer tidak cukup. Tersedia: {$saldo} tabung kosong."
            ]);
        }

        DB::transaction(function () use ($request) {
            // Catat pinjaman
            $pinjamanId = DB::table('pinjaman_tabung')->insertGetId([
                'pangkalan_id'       => $request->pangkalan_id,
                'pihak'              => $request->pihak,
                'nama_pihak'         => $request->nama_pihak,
                'qty_pinjam'         => $request->qty_pinjam,
                'qty_kembali'        => 0,
                'qty_aktif'          => $request->qty_pinjam,
                'tgl_pinjam'         => $request->tgl_pinjam,
                'tgl_berlaku_sampai' => $request->tgl_berlaku_sampai,
                'no_perjanjian'      => $request->no_perjanjian,
                'status'             => 'aktif',
                'keterangan'         => $request->keterangan,
                'created_by'         => auth()->id(),
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);

            // Keluar dari buffer
            DB::table('gudang_tabung_kosong')->insert([
                'jenis'        => 'keluar',
                'tujuan'       => $request->pihak === 'cabang' ? 'pinjam_cabang' : 'pinjam_pangkalan',
                'qty'          => $request->qty_pinjam,
                'tanggal'      => $request->tgl_pinjam,
                'pangkalan_id' => $request->pangkalan_id,
                'no_referensi' => $request->no_perjanjian,
                'keterangan'   => "Pinjaman ke ".($request->pihak === 'cabang' ? $request->nama_pihak : 'pangkalan'),
                'created_by'   => auth()->id(),
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        });

        return back()->with('success',
            "Pinjaman {$request->qty_pinjam} tabung berhasil dicatat."
        );
    }

    // ─────────────────────────────────────────────────────────────
    // CATAT PENGEMBALIAN PINJAMAN
    // ─────────────────────────────────────────────────────────────
    public function pinjamKembali(Request $request, int $pinjamanId)
    {
        $request->validate([
            'qty'       => 'required|integer|min:1',
            'tanggal'   => 'required|date',
            'keterangan'=> 'nullable|string|max:255',
        ]);

        $pinjaman = DB::table('pinjaman_tabung')->find($pinjamanId);
        if (!$pinjaman || $pinjaman->status === 'lunas') {
            return back()->withErrors(['qty' => 'Pinjaman tidak ditemukan atau sudah lunas.']);
        }

        $maxKembali = $pinjaman->qty_aktif;
        if ($request->qty > $maxKembali) {
            return back()->withErrors([
                'qty' => "Maksimal kembali: {$maxKembali} tabung."
            ]);
        }

        DB::transaction(function () use ($request, $pinjaman, $pinjamanId) {
            // Catat pengembalian
            DB::table('pinjaman_tabung_kembali')->insert([
                'pinjaman_id' => $pinjamanId,
                'tanggal'     => $request->tanggal,
                'qty'         => $request->qty,
                'keterangan'  => $request->keterangan,
                'created_by'  => auth()->id(),
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);

            // Update pinjaman
            $qtyKembaliTotal = $pinjaman->qty_kembali + $request->qty;
            $qtyAktif        = $pinjaman->qty_pinjam - $qtyKembaliTotal;
            DB::table('pinjaman_tabung')->where('id', $pinjamanId)->update([
                'qty_kembali' => $qtyKembaliTotal,
                'qty_aktif'   => $qtyAktif,
                'status'      => $qtyAktif <= 0 ? 'lunas' : 'aktif',
                'updated_at'  => now(),
            ]);

            // Masuk kembali ke buffer
            DB::table('gudang_tabung_kosong')->insert([
                'jenis'        => 'masuk',
                'sumber'       => $pinjaman->pihak === 'cabang'
                                    ? 'kembali_cabang' : 'kembali_pangkalan',
                'qty'          => $request->qty,
                'tanggal'      => $request->tanggal,
                'pangkalan_id' => $pinjaman->pangkalan_id,
                'keterangan'   => "Kembali dari pinjaman #{$pinjamanId}",
                'created_by'   => auth()->id(),
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        });

        return back()->with('success',
            "{$request->qty} tabung berhasil dicatat kembali ke buffer."
        );
    }

    // ─────────────────────────────────────────────────────────────
    // OPNAME BUFFER KOSONG
    // ─────────────────────────────────────────────────────────────
    public function opname(Request $request)
    {
        $request->validate([
            'stok_fisik_kosong' => 'required|integer|min:0',
            'stok_fisik_isi'    => 'required|integer|min:0',
            'keterangan'        => 'required|string|max:255',
        ]);

        DB::transaction(function () use ($request) {
            $now = now();

            // Penyesuaian tabung kosong
            $saldoKosong = (int)(
                DB::table('gudang_tabung_kosong')->where('jenis','masuk')->sum('qty') -
                DB::table('gudang_tabung_kosong')->where('jenis','keluar')->sum('qty')
            );
            $selisihKosong = $request->stok_fisik_kosong - $saldoKosong;

            if ($selisihKosong !== 0) {
                DB::table('gudang_tabung_kosong')->insert([
                    'jenis'      => $selisihKosong > 0 ? 'masuk' : 'keluar',
                    'sumber'     => $selisihKosong > 0 ? 'penyesuaian' : null,
                    'tujuan'     => $selisihKosong < 0 ? 'penyesuaian' : null,
                    'qty'        => abs($selisihKosong),
                    'tanggal'    => now()->toDateString(),
                    'keterangan' => "Opname: selisih kosong {$selisihKosong}. ".$request->keterangan,
                    'created_by' => auth()->id(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            // Penyesuaian tabung isi
            $saldoIsi = (int)(
                DB::table('gudang_tabung_isi')->where('jenis','masuk')->sum('qty') -
                DB::table('gudang_tabung_isi')->where('jenis','keluar')->sum('qty')
            );
            $selisihIsi = $request->stok_fisik_isi - $saldoIsi;

            if ($selisihIsi !== 0) {
                DB::table('gudang_tabung_isi')->insert([
                    'jenis'      => $selisihIsi > 0 ? 'masuk' : 'keluar',
                    'sumber'     => $selisihIsi > 0 ? 'penyesuaian' : null,
                    'tujuan'     => $selisihIsi < 0 ? 'penyesuaian' : null,
                    'qty'        => abs($selisihIsi),
                    'tanggal'    => now()->toDateString(),
                    'keterangan' => "Opname: selisih isi {$selisihIsi}. ".$request->keterangan,
                    'created_by' => auth()->id(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });

        return back()->with('success', 'Opname berhasil disimpan.');
    }

    // ─────────────────────────────────────────────────────────────
    // API saldo (untuk realtime di form distribusi)
    // ─────────────────────────────────────────────────────────────
    public function saldoApi()
    {
        $kosong = (int)(DB::table('gudang_tabung_kosong')->where('jenis','masuk')->sum('qty')
                - DB::table('gudang_tabung_kosong')->where('jenis','keluar')->sum('qty'));
        $isi    = (int)(DB::table('gudang_tabung_isi')->where('jenis','masuk')->sum('qty')
                - DB::table('gudang_tabung_isi')->where('jenis','keluar')->sum('qty'));

        return response()->json([
            'kosong'  => $kosong,
            'isi'     => $isi,
            'alert'   => $kosong < self::ALERT_KOSONG_MIN,
        ]);
    }
}
