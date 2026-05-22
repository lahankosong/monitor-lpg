<?php

namespace App\Http\Controllers;

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
class GithubActionsController extends Controller
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
            $sessions = PangkalanSession::where('is_active', true)
                ->whereNotNull('password_encrypted')
                ->get();

            $accounts = $sessions->map(function ($s) {
                try {
                    $pin = Crypt::decryptString($s->password_encrypted);
                } catch (\Exception $e) {
                    Log::warning("[GithubActions] Gagal dekripsi: {$s->label} — {$e->getMessage()}");
                    return null;
                }
                return [
                    'label' => $s->label,
                    'email' => $s->username,
                    'pin'   => $pin,
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
                            $expiresAt = Carbon::createFromTimestamp($payload['exp']);
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
            if (!empty($data['scrape_after']) && $saved > 0) {
                $from = $data['date_from'] ?? now()->toDateString();
                $to   = $data['date_to']   ?? now()->toDateString();

                try {
                    $artisan = base_path('artisan');
                    // Windows
                    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                        pclose(popen(
                            "start /B php \"{$artisan}\" batch:scrape --from={$from} --to={$to}",
                            'r'
                        ));
                    } else {
                        // Linux (server hosting)
                        shell_exec(
                            "php \"{$artisan}\" batch:scrape --from={$from} --to={$to} > /dev/null 2>&1 &"
                        );
                    }
                    Log::info("[GithubActions] batch:scrape dimulai: {$from} s/d {$to}");
                } catch (\Exception $e) {
                    Log::warning('[GithubActions] Gagal trigger scrape: ' . $e->getMessage());
                }
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
}
