<?php

namespace App\Console\Commands;

use App\Models\PangkalanToken;
use App\Models\ScrapeLog;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Artisan command RINGAN — tidak butuh Playwright/browser.
 * Hanya HTTP request biasa menggunakan token yang sudah tersimpan di DB.
 * Token di-refresh oleh GitHub Actions 2x sehari.
 *
 * Usage:
 *   php artisan scrape:transaksi             # semua pangkalan, default date
 *   php artisan scrape:transaksi --from=2026-05-01 --to=2026-05-31
 *   php artisan scrape:transaksi --pangkalan=4531...  # satu pangkalan
 */
class ScrapeTransaksiCommand extends Command
{
    protected $signature = 'scrape:transaksi
                            {--from=  : Tanggal mulai (Y-m-d), default kemarin}
                            {--to=    : Tanggal akhir (Y-m-d), default hari ini}
                            {--pangkalan= : Pangkalan ID spesifik}
                            {--force  : Paksa scrape ulang meski sudah ada}';

    protected $description = 'Scrape transaksi MyPertamina menggunakan token tersimpan (ringan, tanpa browser)';

    // Rate limiting — jangan terlalu cepat
    const DELAY_ANTAR_PANGKALAN = 1;  // detik
    const DELAY_ANTAR_BATCH     = 0.5; // detik
    const BATCH_HARI            = 7;   // request per 7 hari
    const REQUEST_TIMEOUT       = 30;  // detik

    public function handle(): int
    {
        $from      = $this->option('from') ?: Carbon::yesterday()->toDateString();
        $to        = $this->option('to')   ?: Carbon::today()->toDateString();
        $pangId    = $this->option('pangkalan');

        $this->info("Scrape transaksi: {$from} s/d {$to}");
        $this->info("Mode: PHP HTTP (tanpa browser) — cepat & ringan");
        $this->line(str_repeat('─', 60));

        // Ambil token aktif dari database
        $query = PangkalanToken::where('is_active', true)
            ->where('token_expires_at', '>', now()->utc())
            ->whereNotNull('token');

        if ($pangId) {
            $query->where('pangkalan_id', $pangId);
        }

        $tokens = $query->orderBy('pangkalan_id')->get();

        if ($tokens->isEmpty()) {
            $this->error('Tidak ada token aktif di database.');
            $this->warn('Jalankan GitHub Actions untuk refresh token: gh workflow run scrape-tokens.yaml');
            return 1;
        }

        $this->info("Token aktif: {$tokens->count()} pangkalan");

        $berhasil = 0;
        $gagal    = 0;
        $totalTxn = 0;

        foreach ($tokens as $i => $tokenRow) {
            $label = $tokenRow->label ?? $tokenRow->pangkalan_id;
            $this->line("[" . ($i + 1) . "/{$tokens->count()}] {$label}");

            // Cek token masih valid
            if ($tokenRow->token_expires_at && $tokenRow->token_expires_at->isPast()) {
                $this->warn("  ⚠ Token expired sejak {$tokenRow->token_expires_at->diffForHumans()}");
                $gagal++;
                continue;
            }

            $result = $this->scrapeOnePangkalan(
                $tokenRow->pangkalan_id,
                $tokenRow->token,
                $label,
                $from, $to
            );

            if ($result['success']) {
                $berhasil++;
                $totalTxn += $result['saved'];
                $this->info("  ✓ {$result['saved']} transaksi tersimpan");
            } else {
                $gagal++;
                $this->error("  ✗ {$result['error']}");

                // Jika 401 Unauthorized → token expired, tandai
                if ($result['code'] === 401) {
                    $tokenRow->update(['is_active' => false]);
                    $this->warn("  Token di-nonaktifkan. Perlu refresh via GitHub Actions.");
                }
            }

            // Rate limiting
            if ($i < $tokens->count() - 1) {
                sleep(self::DELAY_ANTAR_PANGKALAN);
            }
        }

        $this->line(str_repeat('─', 60));
        $this->info("SELESAI: {$berhasil} berhasil · {$gagal} gagal · {$totalTxn} transaksi baru");

        return $gagal > 0 && $berhasil === 0 ? 1 : 0;
    }

    // ─────────────────────────────────────────────────────────────
    // Scrape satu pangkalan — HTTP request murni, tidak perlu browser
    // ─────────────────────────────────────────────────────────────
    private function scrapeOnePangkalan(
        string $pangkalanId,
        string $token,
        string $label,
        string $from,
        string $to
    ): array {

        $headers = [
            'Authorization' => "Bearer {$token}",
            'Accept'        => 'application/json',
            'User-Agent'    => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'Origin'        => 'https://subsiditepatlpg.mypertamina.id',
            'Referer'       => 'https://subsiditepatlpg.mypertamina.id/',
        ];

        $allCustomers = [];

        // Fetch per batch 7 hari (limit API)
        $current = Carbon::parse($from);
        $end     = Carbon::parse($to);

        while ($current <= $end) {
            $batchEnd = $current->copy()->addDays(self::BATCH_HARI - 1);
            if ($batchEnd > $end) $batchEnd = $end->copy();

            $batchFrom = $current->toDateString();
            $batchTo   = $batchEnd->toDateString();

            $this->line("  → Batch {$batchFrom} s/d {$batchTo}...");

            // HTTP request — tidak butuh browser!
            try {
                $response = Http::withHeaders($headers)
                    ->timeout(self::REQUEST_TIMEOUT)
                    ->retry(3, 2000, fn($e) => true) // retry 3x, delay 2 detik
                    ->get('https://api-map.my-pertamina.id/general/v3/transactions/report', [
                        'search'    => '',
                        'sort'      => 'latest',
                        'startDate' => $batchFrom,
                        'endDate'   => $batchTo,
                    ]);

                if ($response->status() === 401) {
                    return ['success' => false, 'code' => 401,
                            'error' => 'Token expired/invalid (401)'];
                }

                if (!$response->successful()) {
                    return ['success' => false, 'code' => $response->status(),
                            'error' => "HTTP {$response->status()}"];
                }

                $body = $response->json();

                if (!($body['success'] ?? false)) {
                    return ['success' => false, 'code' => 200,
                            'error' => $body['message'] ?? 'API error'];
                }

                $customers = $body['data']['customersReport'] ?? [];
                $allCustomers = array_merge($allCustomers, $customers);
                $this->line("    → {$batchFrom}: " . count($customers) . " transaksi");

            } catch (\Exception $e) {
                return ['success' => false, 'code' => 0, 'error' => $e->getMessage()];
            }

            $current = $batchEnd->addDay();
            usleep((int)(self::DELAY_ANTAR_BATCH * 1_000_000));
        }

        // Simpan ke database
        $saved = $this->simpanTransaksi($pangkalanId, $allCustomers, $from, $to, $label);

        return ['success' => true, 'saved' => $saved, 'code' => 200];
    }

    // ─────────────────────────────────────────────────────────────
    // Simpan transaksi ke database (upsert — tidak duplikat)
    // ─────────────────────────────────────────────────────────────
    private function simpanTransaksi(
        string $pangkalanId,
        array  $customers,
        string $from,
        string $to,
        string $label
    ): int {

        if (empty($customers)) {
            ScrapeLog::create([
                'pangkalan_id'   => $pangkalanId,
                'start_date'     => $from,
                'end_date'       => $to,
                'records_fetched'=> 0,
                'records_saved'  => 0,
                'status'         => 'success',
                'scraped_at'     => now(),
            ]);
            return 0;
        }

        $saved   = 0;
        $skipped = 0;

        DB::transaction(function () use (
            $customers, $pangkalanId, &$saved, &$skipped
        ) {
            foreach ($customers as $c) {
                $txnId = $c['customerReportId'] ?? null;
                if (!$txnId) continue;

                // Upsert — tidak duplikat
                $affected = DB::table('transactions')->upsert([
                    'pangkalan_id'       => $pangkalanId,
                    'customer_report_id' => $txnId,
                    'nationality_id'     => $c['nationalityId']   ?? null,
                    'name'               => $c['name']            ?? null,
                    'categories'         => $c['categories']      ?? null,
                    'total'              => $c['total']           ?? 0,
                    'created_at'         => $c['createdAt']       ?? now(),
                    'transaction_date'   => $c['createdAt']
                                           ? \Carbon\Carbon::parse($c['createdAt'])->toDateString()
                                           : now()->toDateString(),
                    'updated_at'         => now(),
                ], ['customer_report_id'], ['name','total','updated_at']);

                if ($affected > 0) $saved++;
                else $skipped++;
            }
        });

        ScrapeLog::create([
            'pangkalan_id'   => $pangkalanId,
            'start_date'     => $from,
            'end_date'       => $to,
            'records_fetched'=> count($customers),
            'records_saved'  => $saved,
            'status'         => 'success',
            'scraped_at'     => now(),
        ]);

        return $saved;
    }
}
