<?php

namespace App\Http\Controllers\Api;

use App\Models\PangkalanSession;
use App\Models\PangkalanToken;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * API khusus untuk GitHub Actions scraper.
 * Autentikasi via X-API-Key header (bukan session Laravel).
 */
class GithubActionsController
{
    /** GET /api/health — health check untuk scraper */
    public function health()
    {
        return response()->json([
            'status'    => 'ok',
            'timestamp' => now()->toISOString(),
            'db'        => \DB::connection()->getDatabaseName(),
        ]);
    }

    private function validateKey(Request $request): bool
    {
        $key = env('GITHUB_ACTIONS_KEY', '');

        // Jika key belum diset di .env, log warning tapi jangan block
        if (empty($key)) {
            Log::warning('[GithubActions] GITHUB_ACTIONS_KEY belum diset di .env');
            return false;
        }

        return $request->header('X-API-Key') === $key;
    }

    // ─────────────────────────────────────────────────────────────
    // GET /api/github-actions/accounts
    // ─────────────────────────────────────────────────────────────
    public function getAccounts(Request $request)
    {
        if (!$this->validateKey($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        try {
            // Test koneksi DB dulu
            $count = PangkalanSession::count();

            // Ambil semua sesi aktif — utamakan yang punya UUID (bukan pending_)
            $sessions = PangkalanSession::where('is_active', true)
                ->whereNotNull('password_encrypted')
                ->orderByRaw("CASE WHEN pangkalan_id LIKE 'pending_%' THEN 1 ELSE 0 END")
                ->get()
                // Deduplikasi per username — ambil yang UUID dulu, bukan pending_
                ->groupBy('username')
                ->map(fn($g) => $g->first())
                ->values();

            $accounts = $sessions->map(function ($s) {
                try {
                    $pin = Crypt::decryptString($s->password_encrypted);
                    if (empty($pin)) return null;
                } catch (\Exception $e) {
                    Log::warning("[GithubActions] Gagal dekripsi: {$s->label} — {$e->getMessage()}");
                    return null;
                }
                return [
                    'label'      => $s->label ?? $s->username,
                    'email'      => $s->username,
                    'pin'        => $pin,
                    'session_id' => $s->id,
                ];
            })->filter()->values();

            Log::info("[GithubActions] Kirim {$accounts->count()} akun ke scraper");

            return response()->json([
                'success'  => true,
                'total'    => $accounts->count(),
                'accounts' => $accounts,
            ]);

        } catch (\Exception $e) {
            Log::error('[GithubActions] getAccounts error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Server error: ' . $e->getMessage()
            ], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────
    // POST /api/github-actions/tokens
    // ─────────────────────────────────────────────────────────────
    public function receiveTokens(Request $request)
    {
        if (!$this->validateKey($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        try {
            // Validasi manual — hindari ValidationException yang return HTML
            $data = $request->all();

            if (empty($data['tokens']) || !is_array($data['tokens'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Field tokens wajib diisi dan harus array'
                ], 422);
            }

            $saved  = 0;
            $errors = [];

            foreach ($data['tokens'] as $t) {
                try {
                    if (empty($t['token'])) continue;

                    // Decode JWT untuk expire time
                    $pangkalanId = null;
                    $expiresAt   = now()->addHours(12);

                    try {
                        $parts   = explode('.', $t['token']);
                        $pad     = strlen($parts[1] ?? '') % 4;
                        $payload = json_decode(base64_decode(
                            strtr($parts[1] ?? '', '-_', '+/') . ($pad ? str_repeat('=', 4 - $pad) : '')
                        ), true) ?? [];

                        $pangkalanId = $t['pangkalan_id'] ?? ($payload['sub'] ?? null);
                        if (!empty($payload['exp'])) {
                            // Simpan dalam UTC agar konsisten dengan server hosting
                            $expiresAt = Carbon::createFromTimestampUTC($payload['exp']);
                        }
                    } catch (\Exception $e) {
                        $pangkalanId = $t['pangkalan_id'] ?? null;
                    }

                    if (!$pangkalanId) {
                        $errors[] = "Token tanpa pangkalan_id: " . ($t['email'] ?? '?');
                        continue;
                    }

                    PangkalanToken::updateOrCreate(
                        ['pangkalan_id' => $pangkalanId],
                        [
                            'label'            => $t['store_name'] ?? ($t['email'] ?? ''),
                            'token'            => $t['token'],
                            'token_issued_at'  => now(),
                            'token_expires_at' => $expiresAt,
                            'is_active'        => true,
                        ]
                    );

                    // Simpan data stok jika ada dan tabel tersedia
                    // Key dari Railway: stockAvailable, stockRedeem, sold (camelCase)
                    if (\Schema::hasTable('pangkalan_stocks')) {
                        $sd = $t['stock_data'] ?? [];
                        \DB::table('pangkalan_stocks')->updateOrInsert(
                            [
                                'pangkalan_id' => $pangkalanId,
                                'recorded_at'  => now()->toDateString(),
                            ],
                            [
                                'store_name'       => $t['store_name']      ?? null,
                                'stock_available'  => $t['stock_available'] ?? $sd['stockAvailable'] ?? 0,
                                'stock_redeem'     => $t['stock_redeem']    ?? $sd['stockRedeem']    ?? 0,
                                'sold'             => $t['sold']            ?? $sd['sold']            ?? 0,
                                'stock_date'       => $t['stock_date']      ?? null,
                                'last_stock'       => $t['last_stock']      ?? null,
                                'last_stock_date'  => $t['last_stock_date'] ?? null,
                                'updated_at'       => now(),
                                'created_at'       => now(),
                            ]
                        );
                    }

                    // Update pangkalan_id jika masih pending_
                    if (!empty($t['email'])) {
                        PangkalanSession::where('username', $t['email'])
                            ->where('pangkalan_id', 'like', 'pending_%')
                            ->update([
                                'pangkalan_id' => $pangkalanId,
                                'label'        => $t['store_name'] ?? null,
                                'updated_at'   => now(),
                            ]);
                    }

                    $saved++;

                } catch (\Exception $e) {
                    $errors[] = ($t['email'] ?? '?') . ': ' . $e->getMessage();
                    Log::warning('[GithubActions] Token error: ' . $e->getMessage());
                }
            }

            Log::info("[GithubActions] {$saved} token disimpan");
            if ($errors) {
                Log::warning('[GithubActions] Errors: ' . implode(' | ', $errors));
            }

            // Trigger batch scrape jika diminta
            // Di shared hosting shell_exec diblokir — skip, scrape dilakukan manual
            if (!empty($data['scrape_after']) && $saved > 0) {
                $from = $data['date_from'] ?? now()->toDateString();
                $to   = $data['date_to']   ?? now()->toDateString();
                Log::info("[GithubActions] scrape_after diminta tapi skip di hosting (shell_exec diblokir). Jalankan manual: php artisan scrape:transaksi --from={$from} --to={$to}");
            }

            return response()->json([
                'success' => true,
                'message' => "{$saved} token disimpan.",
                'saved'   => $saved,
                'errors'  => $errors,
            ]);

        } catch (\Exception $e) {
            Log::error('[GithubActions] receiveTokens error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Server error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/github-actions/transactions
     * Terima transaksi langsung dari GitHub Actions scraper
     */
    public function receiveTransactions(Request $request)
    {
        if (!$this->validateKey($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $data           = $request->json()->all();
        $pangkalanId    = $data['pangkalan_id']    ?? null; // registration_id dari Pertamina
        $registrationId = $data['registration_id'] ?? $pangkalanId;
        $label          = $data['label']           ?? '';
        $transactions   = $data['transactions']    ?? [];
        $from           = $data['date_from']       ?? now()->toDateString();
        $to             = $data['date_to']         ?? now()->toDateString();

        if (!$pangkalanId) {
            return response()->json(['success' => false, 'message' => 'pangkalan_id wajib diisi'], 422);
        }

        // Sync registration_id ke pangkalan_sessions jika belum ada
        if ($registrationId) {
            PangkalanSession::where('pangkalan_id', 'like', '%' . substr($registrationId, -8) . '%')
                ->orWhere('label', $label)
                ->update(['pangkalan_id' => $registrationId]);
        }

        $saved   = 0;
        $skipped = 0;
        $errors  = [];

        try {
            foreach ($transactions as $c) {
                $txnId = $c['customerReportId'] ?? null;
                if (!$txnId) continue;

                try {
                    // categories dari API adalah array e.g. ["Rumah Tangga"]
                    // ambil elemen pertama sebagai string
                    $cats     = $c['categories'] ?? [];
                    $category = is_array($cats) ? ($cats[0] ?? 'Rumah Tangga') : ($cats ?: 'Rumah Tangga');
                    $txAt = !empty($c['createdAt'])
                        ? \Carbon\Carbon::parse($c['createdAt'])->setTimezone('Asia/Jakarta')
                        : now();

                    \DB::table('transactions')->upsert([
                        'pangkalan_id'       => $pangkalanId,
                        'customer_report_id' => $txnId,
                        'nationality_id'     => $c['nationalityId'] ?? null,
                        'name'               => $c['name']          ?? null,
                        'category'           => $category,
                        'total'              => $c['total']         ?? 0,
                        'transaction_at'     => $txAt,
                        'transaction_date'   => $txAt->toDateString(),
                        'created_at'         => now(),
                        'updated_at'         => now(),
                    ], ['customer_report_id'], ['name', 'total', 'updated_at', 'transaction_at', 'transaction_date']);
                    $saved++;
                } catch (\Exception $e) {
                    $errors[] = $e->getMessage();
                    $skipped++;
                }
            }

            // Catat scrape log
            \DB::table('scrape_logs')->insert([
                'pangkalan_id'    => $pangkalanId,
                'start_date'      => $from,
                'end_date'        => $to,
                'records_fetched' => count($transactions),
                'records_saved'   => $saved,
                'status'          => 'success',
                'scraped_at'      => now(),
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);

            Log::info("[GithubActions] Transaksi diterima: {$label} — {$saved} saved, {$skipped} skip");

            return response()->json([
                'success' => true,
                'message' => "{$saved} transaksi disimpan untuk {$label}",
                'saved'   => $saved,
                'skipped' => $skipped,
                'errors'  => array_slice($errors, 0, 3),
            ]);

        } catch (\Exception $e) {
            Log::error('[GithubActions] receiveTransactions error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}