<?php

namespace App\Jobs;

use App\Models\Transaction;
use App\Models\DailySummary;
use App\Models\NikViolation;
use App\Models\ScrapeLog;
use App\Models\PangkalanToken;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ScrapeTransactionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 1;   // Jangan retry — token sudah expired
    public int $timeout = 120;

    public function __construct(
        public string $startDate,
        public string $endDate,
        public ?string $pangkalanId = null,
    ) {}

    public function handle(): void
    {
        $token = $this->getActiveToken();

        if (! $token) {
            Log::error('[ScrapeJob] Tidak ada token aktif.');
            $this->logScrape('failed', 0, 0, 'Token tidak tersedia atau sudah expired');
            return;
        }

        $result = $this->fetchFromApi($token);

        if (! $result['success']) {
            $this->logScrape('failed', 0, 0, $result['error']);
            return;
        }

        $saved   = $this->saveTransactions($result['transactions']);
        $summary = $result['summary'];

        if ($summary) {
            $this->saveDailySummary($summary);
        }

        $this->detectViolations();
        $this->logScrape('success', count($result['transactions']), $saved);

        Log::info("[ScrapeJob] Selesai: {$saved} transaksi baru disimpan dari "
            . count($result['transactions']) . " data API ({$this->startDate} s/d {$this->endDate})");
    }

    private function fetchFromApi(string $token): array
    {
        $baseUrl = 'https://api-map.my-pertamina.id';
        $webUrl  = 'https://subsiditepatlpg.mypertamina.id';

        $allCustomers = [];
        $lastSummary  = null;
        $start        = Carbon::parse($this->startDate);
        $end          = Carbon::parse($this->endDate);

        try {
            $current = $start->copy();
            while ($current->lte($end)) {
                $batchEnd = $current->copy()->addDays(6)->min($end);

                $response = Http::withHeaders([
                    'Authorization'   => "Bearer {$token}",
                    'Origin'          => $webUrl,
                    'Referer'         => "{$webUrl}/",
                    'Accept'          => 'application/json, text/plain, */*',
                    'Accept-Language' => 'en,en-US;q=0.9,id;q=0.8',
                    'User-Agent'      => 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15',
                ])->timeout(30)->get("{$baseUrl}/general/v3/transactions/report", [
                    'search'    => '',
                    'sort'      => 'latest',
                    'startDate' => $current->format('Y-m-d'),
                    'endDate'   => $batchEnd->format('Y-m-d'),
                ]);

                if ($response->status() === 401) {
                    return ['success' => false, 'error' => 'Token expired (401) — ambil token baru dari browser'];
                }

                if (! $response->successful()) {
                    return ['success' => false, 'error' => "HTTP {$response->status()}: {$response->body()}"];
                }

                $body = $response->json();

                if (! ($body['success'] ?? false)) {
                    return ['success' => false, 'error' => 'API success=false: ' . json_encode($body)];
                }

                $customers    = $body['data']['customersReport'] ?? [];
                $allCustomers = array_merge($allCustomers, $customers);

                if (isset($body['data']['summaryReport'])) {
                    $lastSummary         = $body['data']['summaryReport'];
                    $lastSummary['date'] = $batchEnd->format('Y-m-d');
                }

                sleep(1);
                $current = $batchEnd->copy()->addDay();
            }

            return [
                'success'      => true,
                'transactions' => $allCustomers,
                'summary'      => $lastSummary,
            ];

        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function saveTransactions(array $customers): int
    {
        $saved = 0;

        foreach ($customers as $c) {
            try {
                // Parse waktu transaksi — ini kunci deduplikasi
                $txAt = Carbon::parse($c['createdAt'])->setTimezone('Asia/Jakarta');

                // Cek duplikat berdasarkan NIK + nama + waktu detik
                // customerReportId TIDAK dipakai sebagai kunci unik
                // karena API memberi ID berbeda untuk transaksi yang sama
                $exists = Transaction::where('nationality_id', $c['nationalityId'])
                    ->where('name',           $c['name'])
                    ->where('transaction_at', $txAt)
                    ->exists();

                if ($exists) continue;

                Transaction::create([
                    'customer_report_id' => $c['customerReportId'], // simpan saja, tapi bukan kunci
                    'nationality_id'     => $c['nationalityId'],
                    'name'               => $c['name'],
                    'category'           => $c['categories'][0] ?? 'Rumah Tangga',
                    'total'              => $c['total'],
                    'transaction_at'     => $txAt,
                    'transaction_date'   => $txAt->toDateString(),
                    'pangkalan_id'       => $this->pangkalanId,
                ]);

                $saved++;

            } catch (\Exception $e) {
                Log::warning('[ScrapeJob] Gagal simpan: ' . $e->getMessage());
            }
        }

        return $saved;
    }

    private function saveDailySummary(array $summary): void
    {
        DailySummary::updateOrCreate(
            ['pangkalan_id' => $this->pangkalanId, 'summary_date' => $summary['date']],
            [
                'sold'   => $summary['sold']   ?? 0,
                'modal'  => $summary['modal']  ?? 0,
                'profit' => $summary['profit'] ?? 0,
                'gross'  => $summary['gross']  ?? 0,
            ]
        );
    }

    private function detectViolations(int $minInterval = 7): void
    {
        // Group by NIK+nama (bukan customerReportId)
        $groups = Transaction::whereBetween('transaction_date', [$this->startDate, $this->endDate])
            ->orderBy('transaction_date')
            ->get()
            ->groupBy(fn($t) => $t->nationality_id . '||' . $t->name);

        foreach ($groups as $key => $txns) {
            $dates = $txns->pluck('transaction_date')
                ->map(fn($d) => Carbon::parse($d))
                ->sort()->values();

            for ($i = 1; $i < $dates->count(); $i++) {
                $gap      = $dates[$i - 1]->diffInDays($dates[$i]);
                $severity = $gap < $minInterval * 0.5 ? 'alert' : 'warn';

                if ($gap < $minInterval) {
                    NikViolation::firstOrCreate(
                        [
                            'nationality_id'        => $txns->first()->nationality_id,
                            'prev_transaction_date' => $dates[$i - 1]->toDateString(),
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

    private function getActiveToken(): ?string
    {
        $record = PangkalanToken::where('pangkalan_id', $this->pangkalanId)
            ->where('is_active', true)
            ->first();

        if (! $record) return null;

        if ($record->token_expires_at && $record->token_expires_at->subMinutes(2)->isPast()) {
            Log::warning("[ScrapeJob] Token expired untuk pangkalan {$this->pangkalanId}");
            return null;
        }

        return $record->token;
    }

    private function logScrape(string $status, int $fetched, int $saved, ?string $error = null): void
    {
        ScrapeLog::create([
            'pangkalan_id'    => $this->pangkalanId,
            'start_date'      => $this->startDate,
            'end_date'        => $this->endDate,
            'status'          => $status,
            'records_fetched' => $fetched,
            'records_saved'   => $saved,
            'error_message'   => $error,
            'scraped_at'      => now(),
        ]);
    }
}
