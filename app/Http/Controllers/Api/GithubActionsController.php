<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PangkalanToken;
use App\Models\Pangkalan;
use App\Jobs\ScrapeTransactionsJob;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GithubActionsController extends Controller
{
    /**
     * Endpoint untuk menerima batch token dari GitHub Actions.
     * POST /api/github-actions/tokens
     *
     * Header: X-API-Key: {API_KEY dari .env}
     * Body: {
     *   "tokens": [
     *     {"email": "...", "token": "...", "pangkalan_id": "...", "store_name": "..."},
     *     ...
     *   ],
     *   "scrape_after": true,
     *   "date_from": "2026-05-01",
     *   "date_to": "2026-05-13"
     * }
     */
    public function receiveTokens(Request $request)
    {
        // Validasi API Key
        $apiKey = $request->header('X-API-Key');
        $validKey = config('services.github_actions.api_key');

        if (!$validKey || $apiKey !== $validKey) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid API Key'
            ], 401);
        }

        $tokens = $request->input('tokens', []);
        $scrapeAfter = $request->boolean('scrape_after', true);
        $dateFrom = $request->input('date_from', now()->toDateString());
        $dateTo = $request->input('date_to', now()->toDateString());

        if (empty($tokens)) {
            return response()->json([
                'success' => false,
                'message' => 'No tokens provided'
            ], 400);
        }

        $results = [];
        $savedCount = 0;
        $errorCount = 0;

        foreach ($tokens as $item) {
            $token = trim($item['token'] ?? '');
            $pangkalanId = $item['pangkalan_id'] ?? null;
            $email = $item['email'] ?? null;
            $storeName = $item['store_name'] ?? null;

            if (!$token || !$pangkalanId) {
                $errorCount++;
                $results[] = [
                    'email' => $email,
                    'success' => false,
                    'message' => 'Token atau pangkalan_id kosong'
                ];
                continue;
            }

            try {
                // Decode token untuk dapat expiry
                $expiredAt = null;
                $issuedAt = null;
                try {
                    $parts = explode('.', $token);
                    $payload = json_decode(base64_decode(
                        str_pad(strtr($parts[1], '-_', '+/'), strlen($parts[1]) + (4 - strlen($parts[1]) % 4) % 4, '=')
                    ), true);
                    $expiredAt = isset($payload['exp']) ? Carbon::createFromTimestamp($payload['exp']) : null;
                    $issuedAt = isset($payload['iat']) ? Carbon::createFromTimestamp($payload['iat']) : null;
                } catch (\Exception $e) {
                    // Ignore decode error, continue with token
                }

                // Simpan token
                $record = PangkalanToken::updateOrCreate(
                    ['pangkalan_id' => $pangkalanId],
                    [
                        'token' => $token,
                        'label' => $storeName ?: $email,
                        'token_issued_at' => $issuedAt ?? now(),
                        'token_expires_at' => $expiredAt,
                        'is_active' => true,
                    ]
                );

                // Update nama pangkalan di tabel pangkalans jika ada
                if ($storeName) {
                    Pangkalan::where('map_email', $email)->update([
                        'nama_pangkalan' => $storeName
                    ]);
                }

                $scraped = false;
                $transactionCount = 0;

                // Scrape jika diminta
                if ($scrapeAfter && $expiredAt && $expiredAt->isFuture()) {
                    try {
                        $job = new ScrapeTransactionsJob($dateFrom, $dateTo, $pangkalanId);
                        $job->handle();
                        $scraped = true;

                        // Hitung transaksi yang disimpan
                        $transactionCount = \App\Models\ScrapeLog::where('pangkalan_id', $pangkalanId)
                            ->latest('scraped_at')
                            ->first()?->records_saved ?? 0;
                    } catch (\Exception $e) {
                        Log::error("[GithubActions] Scrape error {$pangkalanId}: " . $e->getMessage());
                    }
                }

                $savedCount++;
                $results[] = [
                    'email' => $email,
                    'pangkalan_id' => $pangkalanId,
                    'store_name' => $storeName,
                    'success' => true,
                    'scraped' => $scraped,
                    'transactions' => $transactionCount,
                ];

            } catch (\Exception $e) {
                $errorCount++;
                $results[] = [
                    'email' => $email,
                    'success' => false,
                    'message' => $e->getMessage()
                ];
                Log::error("[GithubActions] Error saving token {$email}: " . $e->getMessage());
            }
        }

        return response()->json([
            'success' => true,
            'message' => "{$savedCount} token berhasil disimpan, {$errorCount} gagal",
            'summary' => [
                'total' => count($tokens),
                'saved' => $savedCount,
                'errors' => $errorCount,
                'date_range' => "{$dateFrom} - {$dateTo}"
            ],
            'results' => $results
        ]);
    }

    /**
     * Health check endpoint
     */
    public function health()
    {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toISOString(),
            'app' => config('app.name')
        ]);
    }
}
