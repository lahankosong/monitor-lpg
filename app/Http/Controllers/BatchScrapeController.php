<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\DailySummary;
use App\Models\NikViolation;
use App\Models\PangkalanSession;
use App\Models\PangkalanToken;
use App\Models\ScrapeLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class BatchScrapeController extends Controller
{
    private string $pythonPath;
    private string $scriptsPath;
    private string $accountsPath;
    private string $outputPath;

    public function __construct()
    {
        $this->pythonPath   = env('PYTHON_PATH', 'python');
        $this->scriptsPath  = base_path('scripts');
        $this->accountsPath = base_path('scripts/accounts.json');
        $this->outputPath   = storage_path('app/batch_result.json');
    }

    public function index()
    {
        $accounts    = $this->loadAccounts();
        $hasScript   = file_exists($this->scriptsPath . '/auto_login_batch.py');
        $hasAccounts = ! empty($accounts);
        $lastLogs    = ScrapeLog::latest('scraped_at')->limit(20)->get();
        $isRunning   = Cache::get('batch_scrape_running', false);
        $lastResult  = Cache::get('batch_scrape_last_result');

        return view('dashboard.batch-scrape', compact(
            'accounts', 'hasScript', 'hasAccounts',
            'lastLogs', 'isRunning', 'lastResult'
        ));
    }

    public function run(Request $request)
    {
        $request->validate([
            'from' => 'required|date',
            'to'   => 'required|date|after_or_equal:from',
        ]);

        if (! file_exists($this->scriptsPath . '/auto_login_batch.py')) {
            return back()->withErrors(['msg' => 'Script tidak ditemukan di scripts/auto_login_batch.py']);
        }

        if (! file_exists($this->accountsPath)) {
            return back()->withErrors(['msg' => 'accounts.json tidak ditemukan di scripts/']);
        }

        if (Cache::get('batch_scrape_running')) {
            return back()->withErrors(['msg' => 'Batch scraping sedang berjalan. Tunggu selesai dulu.']);
        }

        Cache::put('batch_scrape_running', true, now()->addHours(2));
        Cache::forget('batch_scrape_last_result');

        $resultFile = storage_path('app/batch_result.json');
        if (file_exists($resultFile)) unlink($resultFile);

        $params = [
            'from'        => $request->from,
            'to'          => $request->to,
            'started_at'  => now()->toISOString(),
            'result_file' => $resultFile,
        ];
        file_put_contents(storage_path('app/batch_params.json'), json_encode($params, JSON_PRETTY_PRINT));

        $artisanCmd = sprintf(
            'START /B php %s batch:scrape --from=%s --to=%s',
            base_path('artisan'),
            escapeshellarg($request->from),
            escapeshellarg($request->to)
        );
        pclose(popen($artisanCmd, 'r'));

        Log::info("[BatchScrape] Dimulai background: {$request->from} s/d {$request->to}");

        return redirect()->route('dashboard.batch.status')
            ->with('success', 'Batch scraping dimulai! Proses berjalan di background.');
    }

    public function status()
    {
        $isRunning  = Cache::get('batch_scrape_running', false);
        $progress   = Cache::get('batch_scrape_progress', []);
        $lastResult = Cache::get('batch_scrape_last_result');
        $params     = [];

        if (file_exists(storage_path('app/batch_params.json'))) {
            $params = json_decode(file_get_contents(storage_path('app/batch_params.json')), true) ?? [];
        }

        return view('dashboard.batch-status', compact('isRunning', 'progress', 'lastResult', 'params'));
    }

    public function statusApi()
    {
        return response()->json([
            'running'     => Cache::get('batch_scrape_running', false),
            'progress'    => Cache::get('batch_scrape_progress', []),
            'last_result' => Cache::get('batch_scrape_last_result'),
        ]);
    }

    public function importResult(string $resultFile): array
    {
        if (! file_exists($resultFile)) {
            return ['success' => false, 'error' => 'File hasil tidak ditemukan', 'berhasil' => 0, 'gagal' => 0, 'total_baru' => 0];
        }

        $content     = file_get_contents($resultFile);
        $batchResult = json_decode($content, true);

        if (! $batchResult || ! isset($batchResult['results'])) {
            return ['success' => false, 'error' => 'Format hasil tidak valid', 'berhasil' => 0, 'gagal' => 0, 'total_baru' => 0];
        }

        $stats = ['berhasil' => 0, 'gagal' => 0, 'total_baru' => 0];

        foreach ($batchResult['results'] as $idx => $result) {
            Cache::put('batch_scrape_progress', [
                'current' => $idx + 1,
                'total'   => count($batchResult['results']),
                'label'   => $result['store_name'] ?? $result['label'] ?? '',
            ], now()->addHours(2));

            if (! $result['success'] || empty($result['transactions'])) {
                $stats['gagal']++;
                $this->logScrape(
                    $result['pangkalan_id'] ?? 'unknown',
                    $result['from'] ?? '', $result['to'] ?? '',
                    'failed', 0, 0, $result['error'] ?? 'Login gagal'
                );
                continue;
            }

            $pangkalanId = $result['pangkalan_id'];
            $storeName   = $result['store_name'] ?? $result['label'];

            $this->savePangkalanInfo($result);
            $saved = $this->saveTransactions($result['transactions'], $pangkalanId, $storeName);

            if (! empty($result['summary'])) {
                $this->saveDailySummary($result['summary'], $pangkalanId);
            }

            $this->detectViolations(
                $pangkalanId,
                $result['from'] ?? now()->toDateString(),
                $result['to']   ?? now()->toDateString()
            );

            $this->logScrape(
                $pangkalanId, $result['from'] ?? '', $result['to'] ?? '',
                'success', count($result['transactions']), $saved
            );

            $stats['berhasil']++;
            $stats['total_baru'] += $saved;
        }

        Cache::forget('batch_scrape_running');
        Cache::put('batch_scrape_last_result', $stats, now()->addDay());

        return $stats;
    }

    public function updateAccounts(Request $request)
    {
        $request->validate(['accounts_json' => 'required|string']);
        $accounts = json_decode($request->accounts_json, true);

        if (! is_array($accounts)) {
            return back()->withErrors(['accounts_json' => 'Format JSON tidak valid']);
        }

        foreach ($accounts as $i => $acc) {
            if (empty($acc['email']) || empty($acc['pin'])) {
                return back()->withErrors([
                    'accounts_json' => "Baris ke-" . ($i+1) . " tidak punya field email atau pin"
                ]);
            }
        }

        if (! is_dir($this->scriptsPath)) {
            mkdir($this->scriptsPath, 0755, true);
        }

        file_put_contents(
            $this->accountsPath,
            json_encode($accounts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        return back()->with('success', count($accounts) . ' akun berhasil disimpan.');
    }

    // ── Helpers ──────────────────────────────────────────────────

    private function savePangkalanInfo(array $result): void
    {
        $pangkalanId = $result['pangkalan_id'];

        PangkalanSession::updateOrCreate(
            ['pangkalan_id' => $pangkalanId],
            [
                'label'           => $result['store_name'] ?? $result['label'],
                'username'        => $result['email'],
                'registration_id' => $result['registration_id'] ?? null,
                'last_login_at'   => now(),
                'is_active'       => true,
            ]
        );

        if (! empty($result['token'])) {
            try {
                $parts   = explode('.', $result['token']);
                $payload = json_decode(base64_decode(
                    str_pad(strtr($parts[1], '-_', '+/'),
                    strlen($parts[1]) + (4 - strlen($parts[1]) % 4) % 4, '=')
                ), true);

                PangkalanToken::updateOrCreate(
                    ['pangkalan_id' => $pangkalanId],
                    [
                        'label'            => $result['store_name'] ?? $result['label'],
                        'token'            => $result['token'],
                        'token_issued_at'  => isset($payload['iat']) ? Carbon::createFromTimestamp($payload['iat']) : now(),
                        'token_expires_at' => isset($payload['exp']) ? Carbon::createFromTimestamp($payload['exp']) : null,
                        'is_active'        => true,
                    ]
                );
            } catch (\Exception $e) {
                Log::warning('[BatchScrape] Gagal simpan token: ' . $e->getMessage());
            }
        }

        // Simpan data stok jika tabel sudah ada
        if (
            ! empty($result['stock_available']) &&
            \Illuminate\Support\Facades\Schema::hasTable('pangkalan_stocks')
        ) {
            try {
                \App\Models\PangkalanStock::updateOrCreate(
                    ['pangkalan_id' => $pangkalanId, 'recorded_at' => today()],
                    [
                        'store_name'       => $result['store_name'] ?? null,
                        'registration_id'  => $result['registration_id'] ?? null,
                        'stock_available'  => $result['stock_available'] ?? 0,
                        'stock_redeem'     => $result['stock_redeem'] ?? 0,
                        'sold'             => $result['sold'] ?? 0,
                        'stock_date'       => $result['stock_date'] ?? null,
                        'last_stock'       => $result['last_stock'] ?? 0,
                        'last_stock_date'  => $result['last_stock_date'] ?? null,
                    ]
                );
            } catch (\Exception $e) {
                Log::warning('[BatchScrape] Gagal simpan stok: ' . $e->getMessage());
            }
        }
    }

    private function saveTransactions(array $customers, string $pangkalanId, string $storeName): int
    {
        $saved = 0;
        foreach ($customers as $c) {
            try {
                $txAt   = Carbon::parse($c['createdAt'])->setTimezone('Asia/Jakarta');
                $exists = Transaction::where('nationality_id', $c['nationalityId'])
                    ->where('name',           $c['name'])
                    ->where('transaction_at', $txAt)
                    ->exists();

                if ($exists) continue;

                Transaction::create([
                    'customer_report_id' => $c['customerReportId'],
                    'nationality_id'     => $c['nationalityId'],
                    'name'               => $c['name'],
                    'category'           => $c['categories'][0] ?? 'Rumah Tangga',
                    'total'              => $c['total'],
                    'transaction_at'     => $txAt,
                    'transaction_date'   => $txAt->toDateString(),
                    'pangkalan_id'       => $pangkalanId,
                    'store_name'         => $storeName,
                ]);
                $saved++;
            } catch (\Exception $e) {
                Log::warning('[BatchScrape] Gagal simpan transaksi: ' . $e->getMessage());
            }
        }
        return $saved;
    }

    private function saveDailySummary(array $summary, string $pangkalanId): void
    {
        DailySummary::updateOrCreate(
            ['pangkalan_id' => $pangkalanId, 'summary_date' => $summary['date']],
            [
                'sold'   => $summary['sold']   ?? 0,
                'modal'  => $summary['modal']  ?? 0,
                'profit' => $summary['profit'] ?? 0,
                'gross'  => $summary['gross']  ?? 0,
            ]
        );
    }

    private function detectViolations(string $pangkalanId, string $from, string $to, int $minInterval = 7): void
    {
        if (! $from || ! $to) return;

        $groups = Transaction::where('pangkalan_id', $pangkalanId)
            ->whereBetween('transaction_date', [$from, $to])
            ->orderBy('transaction_date')
            ->get()
            ->groupBy(fn($t) => $t->nationality_id . '||' . $t->name);

        foreach ($groups as $txns) {
            $dates = $txns->pluck('transaction_date')
                ->map(fn($d) => Carbon::parse($d))
                ->sort()->values();

            for ($i = 1; $i < $dates->count(); $i++) {
                $gap      = $dates[$i-1]->diffInDays($dates[$i]);
                $severity = $gap < $minInterval * 0.5 ? 'alert' : 'warn';

                if ($gap < $minInterval) {
                    NikViolation::firstOrCreate(
                        [
                            'nationality_id'        => $txns->first()->nationality_id,
                            'prev_transaction_date' => $dates[$i-1]->toDateString(),
                            'curr_transaction_date' => $dates[$i]->toDateString(),
                        ],
                        [
                            'name'              => $txns->first()->name,
                            'gap_days'          => $gap,
                            'min_interval_days' => $minInterval,
                            'severity'          => $severity,
                        ]
                    );
                }
            }
        }
    }

    private function logScrape(string $pangkalanId, string $from, string $to,
                                string $status, int $fetched, int $saved, ?string $error = null): void
    {
        if (! $from || ! $to) return;
        ScrapeLog::create([
            'pangkalan_id'    => $pangkalanId,
            'start_date'      => $from,
            'end_date'        => $to,
            'status'          => $status,
            'records_fetched' => $fetched,
            'records_saved'   => $saved,
            'error_message'   => $error,
            'scraped_at'      => now(),
        ]);
    }

    private function loadAccounts(): array
    {
        if (! file_exists($this->accountsPath)) return [];
        return json_decode(file_get_contents($this->accountsPath), true) ?? [];
    }
}