<?php

namespace App\Services;

use App\Models\PangkalanSession;
use App\Models\PangkalanToken;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class PlaywrightLoginService
{
    private string $pythonPath;
    private string $scriptPath;

    public function __construct()
    {
        // Path Python — sesuaikan dengan instalasi di komputer Anda
        // Windows XAMPP biasanya: python atau python3
        $this->pythonPath = env('PYTHON_PATH', 'python');
        $this->scriptPath = base_path('scripts/auto_login.py');
    }

    /**
     * Login otomatis ke MyPertamina menggunakan Playwright.
     * Return token dan info pangkalan, atau null jika gagal.
     */
    public function login(string $email, string $pin): ?array
    {
        if (! file_exists($this->scriptPath)) {
            Log::error('[Playwright] Script tidak ditemukan: ' . $this->scriptPath);
            return null;
        }

        $cmd = sprintf(
            '%s %s --email %s --pin %s 2>&1',
            escapeshellcmd($this->pythonPath),
            escapeshellarg($this->scriptPath),
            escapeshellarg($email),
            escapeshellarg($pin),
        );

        Log::info('[Playwright] Menjalankan auto-login untuk: ' . $email);

        $output   = [];
        $exitCode = 0;
        exec($cmd, $output, $exitCode);

        $outputStr = implode("\n", $output);

        // Cari JSON di output (mungkin ada output lain sebelumnya)
        $jsonStart = strrpos($outputStr, '{');
        if ($jsonStart === false) {
            Log::error('[Playwright] Tidak ada JSON di output: ' . $outputStr);
            return null;
        }

        $jsonStr = substr($outputStr, $jsonStart);
        $result  = json_decode($jsonStr, true);

        if (! $result) {
            Log::error('[Playwright] JSON tidak valid: ' . $jsonStr);
            return null;
        }

        if (! $result['success'] || ! $result['token']) {
            Log::warning('[Playwright] Login gagal: ' . ($result['error'] ?? 'unknown'));
            return null;
        }

        Log::info('[Playwright] Login berhasil untuk: ' . ($result['store_name'] ?? $email));
        return $result;
    }

    /**
     * Login, simpan token + info pangkalan, dan return token.
     */
    public function loginAndSave(PangkalanSession $session): ?string
    {
        $password = $session->password;
        if (! $password) {
            Log::warning('[Playwright] Password tidak tersedia untuk: ' . $session->pangkalan_id);
            return null;
        }

        $result = $this->login($session->username, $password);
        if (! $result) return null;

        $pangkalanId = $result['pangkalan_id'] ?? $session->pangkalan_id;

        // Update informasi pangkalan dari API
        if ($result['store_name'] && ! $session->label) {
            $session->update(['label' => $result['store_name']]);
        }

        // Simpan registration_id (ID resmi pangkalan dari Pertamina)
        if ($result['registration_id']) {
            $session->update([
                'registration_id' => $result['registration_id'],
                'last_login_at'   => now(),
            ]);
        }

        // Simpan token
        try {
            $parts   = explode('.', $result['token']);
            $payload = json_decode(base64_decode(
                str_pad(strtr($parts[1], '-_', '+/'),
                strlen($parts[1]) + (4 - strlen($parts[1]) % 4) % 4, '=')
            ), true);

            PangkalanToken::updateOrCreate(
                ['pangkalan_id' => $pangkalanId],
                [
                    'label'            => $result['store_name'] ?? $session->label,
                    'token'            => $result['token'],
                    'token_issued_at'  => isset($payload['iat']) ? Carbon::createFromTimestamp($payload['iat']) : now(),
                    'token_expires_at' => isset($payload['exp']) ? Carbon::createFromTimestamp($payload['exp']) : null,
                    'is_active'        => true,
                ]
            );
        } catch (\Exception $e) {
            Log::error('[Playwright] Gagal simpan token: ' . $e->getMessage());
        }

        return $result['token'];
    }
}
