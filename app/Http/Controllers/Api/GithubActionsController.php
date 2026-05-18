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
    private function validateKey(Request $request): bool
    {
        $key = config('app.github_actions_key',
               env('GITHUB_ACTIONS_KEY', ''));
        return $key && $request->header('X-API-Key') === $key;
    }

    // ─────────────────────────────────────────────────────────────
    // GET /api/github-actions/accounts
    // Kirim daftar akun aktif ke scraper (menggantikan accounts.json)
    // ─────────────────────────────────────────────────────────────
    public function getAccounts(Request $request)
    {
        if (!$this->validateKey($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $sessions = PangkalanSession::where('is_active', true)
            ->whereNotNull('password_encrypted')
            ->get();

        $accounts = $sessions->map(function ($s) {
            try {
                $pin = Crypt::decryptString($s->password_encrypted);
            } catch (\Exception $e) {
                // Skip akun yang passwordnya tidak bisa didekripsi
                Log::warning("[GithubActions] Gagal dekripsi password: {$s->label}");
                return null;
            }

            return [
                'label' => $s->label,
                'email' => $s->username,
                'pin'   => $pin,
            ];
        })->filter()->values();

        Log::info("[GithubActions] Mengirim {$accounts->count()} akun ke scraper");

        return response()->json([
            'success'  => true,
            'total'    => $accounts->count(),
            'accounts' => $accounts,
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // POST /api/github-actions/tokens
    // Terima batch token dari scraper → simpan ke DB → trigger scrape
    // ─────────────────────────────────────────────────────────────
    public function receiveTokens(Request $request)
    {
        if (!$this->validateKey($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $request->validate([
            'tokens'       => 'required|array',
            'tokens.*.email'  => 'required|string',
            'tokens.*.token'  => 'required|string',
            'scrape_after' => 'boolean',
            'date_from'    => 'nullable|date',
            'date_to'      => 'nullable|date',
        ]);

        $saved = 0;
        foreach ($request->tokens as $t) {
            try {
                // Decode JWT untuk dapat expire time
                $parts   = explode('.', $t['token']);
                $payload = json_decode(base64_decode(
                    str_pad(strtr($parts[1] ?? '', '-_', '+/'),
                    strlen($parts[1] ?? '') + (4 - strlen($parts[1] ?? '') % 4) % 4, '=')
                ), true) ?? [];

                $pangkalanId = $t['pangkalan_id'] ?? ($payload['sub'] ?? null);
                $expiresAt   = isset($payload['exp'])
                    ? Carbon::createFromTimestamp($payload['exp'])
                    : now()->addHours(12);

                if (!$pangkalanId) continue;

                PangkalanToken::updateOrCreate(
                    ['pangkalan_id' => $pangkalanId],
                    [
                        'label'            => $t['store_name'] ?? $t['email'],
                        'token'            => $t['token'],
                        'token_issued_at'  => now(),
                        'token_expires_at' => $expiresAt,
                        'is_active'        => true,
                    ]
                );

                // Update pangkalan_id di session jika masih pending_
                PangkalanSession::where('username', $t['email'])
                    ->where('pangkalan_id', 'like', 'pending_%')
                    ->update([
                        'pangkalan_id' => $pangkalanId,
                        'label'        => $t['store_name'] ?? null,
                    ]);

                $saved++;
            } catch (\Exception $e) {
                Log::warning("[GithubActions] Gagal simpan token: " . $e->getMessage());
            }
        }

        Log::info("[GithubActions] {$saved} token disimpan dari GitHub Actions");

        // Trigger scrape jika diminta
        if ($request->boolean('scrape_after') && $saved > 0) {
            $from = $request->input('date_from', now()->toDateString());
            $to   = $request->input('date_to',   now()->toDateString());

            // Jalankan artisan command di background
            $artisan = base_path('artisan');
            pclose(popen(
                sprintf('start /B php %s batch:scrape --from=%s --to=%s',
                    escapeshellarg($artisan),
                    escapeshellarg($from),
                    escapeshellarg($to)
                ), 'r'
            ));

            Log::info("[GithubActions] Batch scrape dimulai: {$from} s/d {$to}");
        }

        return response()->json([
            'success' => true,
            'message' => "{$saved} token disimpan.",
            'saved'   => $saved,
        ]);
    }
}
