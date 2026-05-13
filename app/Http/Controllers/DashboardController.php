<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\NikViolation;
use App\Models\DailySummary;
use App\Models\PangkalanToken;
use App\Models\PangkalanSession;
use App\Models\ScrapeLog;
use App\Services\NikMonitorService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private NikMonitorService $monitor) {}

    // ── Dashboard utama ───────────────────────────────────────────

    public function index(Request $request)
    {
        $from        = $request->get('from', now()->startOfMonth()->toDateString());
        $to          = $request->get('to',   now()->toDateString());
        $minInterval = (int) $request->get('interval', 7);
        $pangkalanId = $request->get('pangkalan_id', '');

        $query = Transaction::dateRange($from, $to);
        if ($pangkalanId) $query->where('pangkalan_id', $pangkalanId);

        $txns   = $query->orderBy('transaction_date')->get();
        $groups = $txns->groupBy(fn($t) => $t->nationality_id . '||' . $t->name)
                       ->map(fn($g) => $this->monitor->analyzeIndividu($g));

        $nikStatus  = $this->monitor->nikStatusSummary($groups);
        $totalNik   = $groups->count();

        $summaryQ = DailySummary::whereBetween('summary_date', [$from, $to]);
        if ($pangkalanId) $summaryQ->where('pangkalan_id', $pangkalanId);
        $summary     = $summaryQ->get();
        $totalSold   = $summary->sum('sold');
        $totalGross  = $summary->sum('gross');
        $totalProfit = $summary->sum('profit');

        $openViolations = NikViolation::unresolved()->orderBy('severity', 'desc')->limit(10)->get();

        $chartDays = collect();
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $q    = Transaction::where('transaction_date', $date);
            if ($pangkalanId) $q->where('pangkalan_id', $pangkalanId);
            $chartDays->push([
                'date'  => $date,
                'label' => Carbon::parse($date)->format('d/m'),
                'sold'  => DailySummary::where('summary_date', $date)
                    ->when($pangkalanId, fn($q) => $q->where('pangkalan_id', $pangkalanId))
                    ->sum('sold'),
                'txn'   => $q->count(),
            ]);
        }

        $lastScrape  = ScrapeLog::latest('scraped_at')->first();
        $pangkalans  = $this->getPangkalanList();

        return view('dashboard.index', compact(
            'nikStatus', 'totalNik', 'from', 'to', 'minInterval',
            'totalSold', 'totalGross', 'totalProfit',
            'openViolations', 'chartDays', 'lastScrape',
            'pangkalans', 'pangkalanId'
        ));
    }

    // ── Monitor NIK ───────────────────────────────────────────────

    public function nikList(Request $request)
    {
        $from         = $request->get('from', now()->startOfMonth()->toDateString());
        $to           = $request->get('to',   now()->toDateString());
        $minInterval  = (int) $request->get('interval', 7);
        $search       = $request->get('search', '');
        $filterStatus = $request->get('status', '');
        $pangkalanId  = $request->get('pangkalan_id', '');
        $filterKat    = $request->get('kategori', '');

        $query = Transaction::dateRange($from, $to)
            ->when($search, fn($q) => $q->where(function ($q) use ($search) {
                $q->where('nationality_id', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            }))
            ->when($pangkalanId, fn($q) => $q->where('pangkalan_id', $pangkalanId))
            ->when($filterKat,   fn($q) => $q->where('category', 'like', "%{$filterKat}%"))
            ->orderBy('transaction_date')
            ->get();

        // Analisis semua individu
        $groups = $query->groupBy(fn($t) => $t->nationality_id . '||' . $t->name)
                        ->map(fn($g) => $this->monitor->analyzeIndividu($g, $minInterval));

        // Cek pelanggaran pengecer di level pangkalan
        $pengecerWarnings = $this->monitor->checkPengecer($query);

        // Filter status
        if ($filterStatus) {
            $groups = $groups->filter(fn($n) => $n['status'] === $filterStatus);
        }

        // Urutkan: alert → warn → new → aman
        $order  = ['alert' => 0, 'warn' => 1, 'new' => 2, 'aman' => 3];
        $groups = $groups->sortBy(fn($n) => $order[$n['status']] ?? 9)->values();

        $pangkalans = $this->getPangkalanList();

        return view('dashboard.nik', compact(
            'groups', 'from', 'to', 'minInterval', 'search',
            'filterStatus', 'pangkalanId', 'filterKat',
            'pangkalans', 'pengecerWarnings'
        ));
    }

    // ── Detail NIK ────────────────────────────────────────────────

    public function nikDetail(Request $request)
    {
        $from = $request->get('from', now()->subMonths(3)->toDateString());
        $to   = $request->get('to',   now()->toDateString());
        $nik  = $request->get('nik');
        $nama = $request->get('nama');

        abort_if(! $nik || ! $nama, 400);

        $txns = Transaction::where('nationality_id', $nik)
            ->where('name', $nama)
            ->dateRange($from, $to)
            ->orderBy('transaction_at')
            ->get();

        abort_if($txns->isEmpty(), 404);

        $minInterval = (int) $request->get('interval', 7);
        $analysis    = $this->monitor->analyzeIndividu($txns, $minInterval);
        $pangkalans  = $this->getPangkalanList();

        return view('dashboard.nik-detail', compact(
            'txns', 'nik', 'nama', 'analysis', 'from', 'to', 'minInterval', 'pangkalans'
        ));
    }

    // ── Ekspor CSV ────────────────────────────────────────────────

    public function exportCsv(Request $request)
    {
        $from        = $request->get('from', now()->startOfMonth()->toDateString());
        $to          = $request->get('to',   now()->toDateString());
        $pangkalanId = $request->get('pangkalan_id', '');

        $txns = Transaction::dateRange($from, $to)
            ->when($pangkalanId, fn($q) => $q->where('pangkalan_id', $pangkalanId))
            ->orderBy('transaction_date')->get();

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=transaksi_{$from}_{$to}.csv",
        ];

        $callback = function () use ($txns) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, ['Pangkalan','NIK (sensor)','Nama','Kategori','Tabung','Tanggal','Waktu']);
            foreach ($txns as $t) {
                fputcsv($out, [
                    $t->store_name ?? $t->pangkalan_id,
                    $t->nationality_id,
                    $t->name,
                    $t->category,
                    $t->total,
                    $t->transaction_date->format('Y-m-d'),
                    Carbon::parse($t->transaction_at)->format('H:i:s'),
                ]);
            }
            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
    }

    // ── Trigger scrape manual ─────────────────────────────────────

    public function triggerScrape(Request $request)
    {
        $request->validate(['from' => 'required|date', 'to' => 'required|date']);
        $tokenRecord = PangkalanToken::where('is_active', true)->latest()->first();
        if (! $tokenRecord) {
            return back()->withErrors(['token' => 'Belum ada token aktif.']);
        }
        \App\Jobs\ScrapeTransactionsJob::dispatch($request->from, $request->to, $tokenRecord->pangkalan_id);
        return back()->with('success', "Scraping dijadwalkan: {$request->from} s/d {$request->to}");
    }

    public function saveToken(Request $request)
    {
        $request->validate(['token' => 'required|string', 'label' => 'nullable|string']);
        $token = trim(str_replace('Bearer ', '', $request->token));
        try {
            $parts   = explode('.', $token);
            $payload = json_decode(base64_decode(
                str_pad(strtr($parts[1], '-_', '+/'),
                strlen($parts[1]) + (4 - strlen($parts[1]) % 4) % 4, '=')
            ), true);
            $pangkalanId = $payload['sub'] ?? null;
            $expireAt    = isset($payload['exp']) ? Carbon::createFromTimestamp($payload['exp']) : null;
        } catch (\Exception $e) {
            return back()->withErrors(['token' => 'Token tidak valid.']);
        }
        PangkalanToken::updateOrCreate(
            ['pangkalan_id' => $pangkalanId],
            ['label' => $request->label, 'token' => $token,
             'token_expires_at' => $expireAt, 'is_active' => true]
        );
        return back()->with('success', 'Token disimpan.');
    }

    // ── Helper ───────────────────────────────────────────────────

    /**
     * Daftar pangkalan untuk dropdown — diurutkan alfabet
     */
    private function getPangkalanList(): \Illuminate\Support\Collection
    {
        return PangkalanToken::orderBy('label')
            ->get(['pangkalan_id', 'label'])
            ->map(fn($p) => [
                'id'    => $p->pangkalan_id,
                'label' => $p->label ?: substr($p->pangkalan_id, 0, 8) . '...',
            ]);
    }
}
