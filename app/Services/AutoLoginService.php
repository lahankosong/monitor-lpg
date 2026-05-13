<?php

namespace App\Services;

use App\Models\PangkalanSession;
use App\Models\PangkalanToken;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AutoLoginService
{
    private string $webUrl  = 'https://subsiditepatlpg.mypertamina.id';
    private string $apiUrl  = 'https://api-map.my-pertamina.id';

    /**
     * Coba ambil token aktif untuk satu pangkalan.
     * Urutan: token masih valid → pakai cookies → login ulang (jika ada password)
     */
    public function getActiveToken(string $pangkalanId): ?string
    {
        // 1. Cek apakah token masih valid
        $tokenRecord = PangkalanToken::where('pangkalan_id', $pangkalanId)
            ->where('is_active', true)->first();

        if ($tokenRecord && $tokenRecord->token_expires_at?->isFuture()) {
            Log::info("[AutoLogin] Token masih valid untuk: {$pangkalanId}");
            return $tokenRecord->token;
        }

        // 2. Coba refresh token pakai cookies yang tersimpan
        $session = PangkalanSession::where('pangkalan_id', $pangkalanId)
            ->where('is_active', true)->first();

        if ($session && $session->getCookiesData()) {
            $token = $this->refreshTokenWithCookies($session);
            if ($token) return $token;
        }

        // 3. Tidak bisa auto-login — butuh intervensi manual
        Log::warning("[AutoLogin] Tidak bisa auto-login untuk: {$pangkalanId}");
        return null;
    }

    /**
     * Pakai cookies tersimpan untuk hit API dan ambil token baru.
     * MyPertamina pakai JWT yang di-refresh via cookies session.
     */
    public function refreshTokenWithCookies(PangkalanSession $session): ?string
    {
        $cookies = $session->getCookiesData();
        if (empty($cookies)) return null;

        // Format cookies untuk header HTTP
        $cookieStr = collect($cookies)
            ->map(fn($c) => "{$c['name']}={$c['value']}")
            ->implode('; ');

        try {
            // Hit endpoint yang membutuhkan auth — ini akan me-return token baru
            // atau redirect ke login jika session expired
            $response = Http::withHeaders([
                'Cookie'          => $cookieStr,
                'Origin'          => $this->webUrl,
                'Referer'         => "{$this->webUrl}/merchant/app",
                'Accept'          => 'application/json, text/plain, */*',
                'Accept-Language' => 'en,en-US;q=0.9,id;q=0.8',
                'User-Agent'      => 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15',
            ])->timeout(15)->get("{$this->apiUrl}/general/v3/transactions/report", [
                'search'    => '',
                'sort'      => 'latest',
                'startDate' => now()->toDateString(),
                'endDate'   => now()->toDateString(),
            ]);

            // Kalau 401 = cookies expired
            if ($response->status() === 401) {
                Log::warning("[AutoLogin] Cookies expired untuk: {$session->pangkalan_id}");
                return null;
            }

            // Cek apakah ada token baru di response header Authorization
            $authHeader = $response->header('Authorization');
            if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
                $newToken = str_replace('Bearer ', '', $authHeader);
                $this->saveToken($session->pangkalan_id, $newToken);
                Log::info("[AutoLogin] Token di-refresh via cookies: {$session->pangkalan_id}");
                return $newToken;
            }

            // Kalau response 200 tapi tidak ada token baru di header,
            // token lama di cookies masih berlaku — coba ambil dari storage
            if ($response->successful()) {
                // Coba ambil dari token record yang mungkin masih ada
                $tokenRecord = PangkalanToken::where('pangkalan_id', $session->pangkalan_id)->first();
                if ($tokenRecord?->token) {
                    Log::info("[AutoLogin] Pakai token tersimpan (cookies masih valid): {$session->pangkalan_id}");
                    return $tokenRecord->token;
                }
            }

            return null;

        } catch (\Exception $e) {
            Log::error("[AutoLogin] Error refresh cookies: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Simpan token baru ke database.
     */
    public function saveToken(string $pangkalanId, string $token): void
    {
        try {
            $parts   = explode('.', $token);
            $payload = json_decode(base64_decode(
                str_pad(strtr($parts[1], '-_', '+/'),
                strlen($parts[1]) + (4 - strlen($parts[1]) % 4) % 4, '=')
            ), true);

            PangkalanToken::updateOrCreate(
                ['pangkalan_id' => $pangkalanId],
                [
                    'token'            => $token,
                    'token_issued_at'  => isset($payload['iat']) ? Carbon::createFromTimestamp($payload['iat']) : now(),
                    'token_expires_at' => isset($payload['exp']) ? Carbon::createFromTimestamp($payload['exp']) : null,
                    'is_active'        => true,
                ]
            );
        } catch (\Exception $e) {
            Log::error("[AutoLogin] Gagal simpan token: " . $e->getMessage());
        }
    }

    /**
     * Cek status semua pangkalan sekaligus.
     * Return array status per pangkalan_id.
     */
    public function checkAllStatus(): array
    {
        $sessions = PangkalanSession::where('is_active', true)->get();
        $result   = [];

        foreach ($sessions as $session) {
            $tokenRecord = PangkalanToken::where('pangkalan_id', $session->pangkalan_id)->first();

            $result[$session->pangkalan_id] = [
                'label'          => $session->label,
                'token_valid'    => $tokenRecord?->token_expires_at?->isFuture() ?? false,
                'token_expires'  => $tokenRecord?->token_expires_at?->format('H:i d/m'),
                'cookies_fresh'  => $session->isCookiesFresh(),
                'cookies_age'    => $session->cookies_captured_at?->diffForHumans(),
                'needs_relogin'  => ! $session->isCookiesFresh(),
            ];
        }

        return $result;
    }
}
