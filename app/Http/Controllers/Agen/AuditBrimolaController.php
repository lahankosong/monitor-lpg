<?php

namespace App\Http\Controllers\Agen;

use App\Http\Controllers\Controller;
use App\Models\Pangkalan;
use App\Services\AuditAlokasiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuditBrimolaController extends Controller
{
    public function __construct(private AuditAlokasiService $audit) {}

    /** Halaman audit — list semua pangkalan dengan saldo */
    public function index(Request $request)
    {
        $status = $request->get('status', '');
        $search = $request->get('search', '');

        $pangkalans = DB::table('pangkalans as p')
            ->leftJoin('saldo_pangkalan as s', 'p.id', '=', 's.pangkalan_id')
            ->when($search, fn($q) => $q->where('p.nama_pangkalan', 'like', "%{$search}%"))
            ->when($status, fn($q) => $q->where('s.status', $status))
            ->select(
                'p.id', 'p.nama_pangkalan', 'p.no_reg', 'p.tipe as tipe_pangkalan',
                's.total_dibayar', 's.total_didistribusi', 's.saldo_tabung',
                's.total_nilai_bayar', 's.total_nilai_distribusi', 's.saldo_nilai',
                's.status', 's.last_calculated_at'
            )
            ->orderByRaw("CASE s.status WHEN 'piutang' THEN 1 WHEN 'saldo_kredit' THEN 2 ELSE 3 END")
            ->orderByRaw('ABS(s.saldo_tabung) DESC')
            ->paginate(30)->withQueryString();

        // Total summary
        $summary = DB::table('saldo_pangkalan')
            ->selectRaw("
                SUM(CASE WHEN status='piutang'      THEN ABS(saldo_tabung) ELSE 0 END) as total_piutang_tb,
                SUM(CASE WHEN status='piutang'      THEN ABS(saldo_nilai)  ELSE 0 END) as total_piutang_rp,
                SUM(CASE WHEN status='saldo_kredit' THEN saldo_tabung ELSE 0 END) as total_kredit_tb,
                SUM(CASE WHEN status='saldo_kredit' THEN saldo_nilai  ELSE 0 END) as total_kredit_rp,
                SUM(CASE WHEN status='piutang'      THEN 1 ELSE 0 END) as jml_piutang,
                SUM(CASE WHEN status='saldo_kredit' THEN 1 ELSE 0 END) as jml_kredit,
                SUM(CASE WHEN status='lunas'        THEN 1 ELSE 0 END) as jml_lunas
            ")->first();

        return view('agen.akuntansi.brimola.audit', compact(
            'pangkalans','summary','status','search'
        ));
    }

    /** Halaman detail audit per pangkalan */
    public function detail(int $pangkalanId)
    {
        $pangkalan = Pangkalan::findOrFail($pangkalanId);

        $saldo = DB::table('saldo_pangkalan')
            ->where('pangkalan_id', $pangkalanId)->first();

        $timeline = $this->audit->timelinePangkalan($pangkalanId);

        // Ambil transaksi BRImola untuk verifikasi
        $brimolaTransaksi = DB::table('brimola_transaksi')
            ->where('pangkalan_id', $pangkalanId)
            ->orderByDesc('tanggal_bayar')
            ->get();

        // Hitung summary per status
        $brimolaStats = DB::table('brimola_transaksi')
            ->where('pangkalan_id', $pangkalanId)
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status='matched' THEN 1 ELSE 0 END) as matched,
                SUM(CASE WHEN status='verified' THEN 1 ELSE 0 END) as verified,
                SUM(CASE WHEN status='unmatched' THEN 1 ELSE 0 END) as unmatched
            ")->first();

        return view('agen.akuntansi.brimola.audit-detail', compact(
            'pangkalan','saldo','timeline','brimolaTransaksi','brimolaStats'
        ));
    }

    /** Verifikasi transaksi BRImola dari halaman audit */
    public function verify(Request $request, int $pangkalanId)
    {
        $request->validate(['transaksi_ids' => 'required|array']);

        DB::table('brimola_transaksi')
            ->whereIn('id', $request->transaksi_ids)
            ->where('pangkalan_id', $pangkalanId)
            ->where('status', 'matched')
            ->update(['status' => 'verified', 'updated_at' => now()]);

        return back()->with('success', count($request->transaksi_ids).' transaksi berhasil diverifikasi.');
    }

    /** Verifikasi semua transaksi matched untuk pangkalan */
    public function verifyAll(int $pangkalanId)
    {
        $count = DB::table('brimola_transaksi')
            ->where('pangkalan_id', $pangkalanId)
            ->where('status', 'matched')
            ->update(['status' => 'verified', 'updated_at' => now()]);

        return back()->with('success', $count.' transaksi berhasil diverifikasi.');
    }

    /** Re-alokasi semua pangkalan (bulk) */
    public function realokasiSemua()
    {
        $hasil = $this->audit->alokasiSemua();
        $msg = "Re-alokasi selesai: "
             . "{$hasil['lunas']} lunas, "
             . "{$hasil['kredit']} saldo kredit, "
             . "{$hasil['piutang']} piutang.";
        return back()->with('success', $msg);
    }

    /** Re-alokasi satu pangkalan */
    public function realokasiPangkalan(int $pangkalanId)
    {
        $this->audit->alokasiPangkalan($pangkalanId);
        return back()->with('success', 'Alokasi pangkalan berhasil di-update.');
    }
}
