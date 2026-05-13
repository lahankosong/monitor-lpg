<?php

namespace App\Http\Controllers\Map;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\PangkalanSession;
use App\Models\PangkalanStock;
use App\Models\NikViolation;
use App\Services\NikMonitorService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MapDashboardController extends Controller
{
    public function __construct(private NikMonitorService $monitor) {}

    public function index(Request $request)
    {
        $from = $request->get('from', now()->startOfMonth()->toDateString());
        $to   = $request->get('to',   now()->toDateString());

        // ── Total penyaluran (dari tabel stok) vs transaksi ──────
        $totalStokDisalurkan = PangkalanStock::where('recorded_at', '>=', $from)
            ->where('recorded_at', '<=', $to)
            ->sum('stock_redeem'); // total tabung yang sudah ditebus/disalurkan

        $totalTransaksiTabung = Transaction::whereBetween('transaction_date', [$from, $to])
            ->sum('total'); // total tabung dari semua transaksi

        $totalTransaksiCount = Transaction::whereBetween('transaction_date', [$from, $to])
            ->count();

        $totalNik = Transaction::whereBetween('transaction_date', [$from, $to])
            ->distinct()
            ->count(DB::raw("CONCAT(nationality_id, '||', name)"));

        // ── Data per pangkalan ────────────────────────────────────
        $sessions = PangkalanSession::orderBy('label')->get();

        $pangkalans = $sessions->map(function ($s) use ($from, $to) {
            // Transaksi periode ini
            $txns = Transaction::where('pangkalan_id', $s->pangkalan_id)
                ->whereBetween('transaction_date', [$from, $to])
                ->get();

            $totalTabung   = $txns->sum('total');
            $totalTxnCount = $txns->count();

            // Stok terbaru
            $stok = PangkalanStock::where('pangkalan_id', $s->pangkalan_id)
                ->orderBy('recorded_at', 'desc')
                ->first();

            // Pelanggaran
            $groups     = $txns->groupBy(fn($t) => $t->nationality_id . '||' . $t->name);
            $violations = [];
            foreach ($groups as $g) {
                $analysis = $this->monitor->analyzeIndividu($g);
                if (! empty($analysis['all_alerts'])) {
                    foreach ($analysis['all_alerts'] as $v) {
                        $type = $v['type'] ?? 'unknown';
                        $violations[$type] = ($violations[$type] ?? 0) + 1;
                    }
                }
            }

            // Ringkasan per tipe konsumen
            $perTipe = $txns->groupBy('category')->map(fn($g) => [
                'count'  => $g->count(),
                'tabung' => $g->sum('total'),
            ]);

            return [
                'id'             => $s->pangkalan_id,
                'label'          => $s->label,
                'registration_id'=> $s->registration_id,
                'is_active'      => $s->is_active,
                'total_tabung'   => $totalTabung,
                'total_txn'      => $totalTxnCount,
                'total_nik'      => $groups->count(),
                'stok_available' => $stok?->stock_available ?? 0,
                'stok_redeem'    => $stok?->stock_redeem ?? 0,
                'sold'           => $stok?->sold ?? 0,
                'violations'     => $violations,
                'has_violation'  => ! empty($violations),
                'per_tipe'       => $perTipe,
            ];
        });

        // ── Grafik 7 hari terakhir ────────────────────────────────
        $chartDays = collect();
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $chartDays->push([
                'label'  => Carbon::parse($date)->format('d/m'),
                'tabung' => Transaction::where('transaction_date', $date)->sum('total'),
                'txn'    => Transaction::where('transaction_date', $date)->count(),
            ]);
        }

        // ── Summary cards ─────────────────────────────────────────
        $totalPangkalan      = $sessions->count();
        $pangkalanAktif      = $sessions->where('is_active', true)->count();
        $pangkalanViolation  = $pangkalans->where('has_violation', true)->count();

        return view('map.dashboard', compact(
            'from', 'to',
            'totalStokDisalurkan', 'totalTransaksiTabung',
            'totalTransaksiCount', 'totalNik',
            'totalPangkalan', 'pangkalanAktif', 'pangkalanViolation',
            'pangkalans', 'chartDays'
        ));
    }
}