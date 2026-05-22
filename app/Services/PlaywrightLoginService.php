<?php

namespace App\Services;

use App\Models\PangkalanSession;
use App\Models\PangkalanToken;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Crypt;

class PlaywrightLoginService
{
    private string $pythonPath;
    private string $scriptPath;
    private int $timeout = 120; // 2 menit timeout
    private int $maxRetries = 3;

    public function __construct()
    {
        $this->pythonPath = env('PYTHON_PATH', 'python');
        $this->scriptPath = base_path('scripts/auto_login.py');
    }

    /**
     * Login otomatis ke MyPertamina menggunakan Playwright dengan retry mechanism.
     * Return token dan info pangkalan, atau null jika gagal.
     */
    public function login(string $email, string $pin, int $retryCount = 0): ?array
    {
        if (! file_exists($this->scriptPath)) {
            Log::error('[Playwright] Script tidak ditemukan: ' . $this->scriptPath);
            return null;
        }

        // Validasi input
        if (empty($email) || empty($pin)) {
            Log::error('[Playwright] Email atau PIN kosong');
            return null;
        }

        $cmd = sprintf(
            '%s %s --email %s --pin %s 2>&1',
            escapeshellcmd($this->pythonPath),
            escapeshellarg($this->scriptPath),
            escapeshellarg($email),
            escapeshellarg($pin),
        );

        Log::info('[Playwright] Menjalankan auto-login untuk: ' . $email . ' (attempt ' . ($retryCount + 1) . ')');

        // Eksekusi dengan timeout menggunakan proc_open
        $output = $this->executeWithTimeout($cmd, $this->timeout);
        
        if ($output === null) {
            Log::error('[Playwright] Timeout atau eksekusi gagal untuk: ' . $email);
            
            // Retry jika masih dalam batas
            if ($retryCount < $this->maxRetries) {
                Log::info('[Playwright] Retry login untuk: ' . $email . ' (' . ($retryCount + 1) . '/' . $this->maxRetries . ')');
                sleep(5); // Delay sebelum retry
                return $this->login($email, $pin, $retryCount + 1);
            }
            
            return null;
        }

        $outputStr = implode("\n", $output);

        // Cari JSON di output (mungkin ada output lain sebelumnya)
        $jsonStart = strrpos($outputStr, '{');
        if ($jsonStart === false) {
            Log::error('[Playwright] Tidak ada JSON di output: ' . $outputStr);
            
            // Retry jika masih dalam batas
            if ($retryCount < $this->maxRetries) {
                Log::info('[Playwright] Retry login untuk: ' . $email);
                sleep(5);
                return $this->login($email, $pin, $retryCount + 1);
            }
            
            return null;
        }

        $jsonStr = substr($outputStr, $jsonStart);
        $result  = json_decode($jsonStr, true);

        if (! $result) {
            Log::error('[Playwright] JSON tidak valid: ' . $jsonStr);
            
            if ($retryCount < $this->maxRetries) {
                sleep(5);
                return $this->login($email, $pin, $retryCount + 1);
            }
            
            return null;
        }

        if (! isset($result['success']) || ! $result['success'] || ! isset($result['token'])) {
            $errorMsg = $result['error'] ?? 'unknown';
            Log::warning('[Playwright] Login gagal untuk ' . $email . ': ' . $errorMsg);
            
            // Retry untuk error tertentu (timeout, network, dll) tapi tidak untuk invalid credentials
            $retryableErrors = ['timeout', 'network', 'connection', 'browser error'];
            $isRetryable = false;
            foreach ($retryableErrors as $retryable) {
                if (stripos($errorMsg, $retryable) !== false) {
                    $isRetryable = true;
                    break;
                }
            }
            
            if ($isRetryable && $retryCount < $this->maxRetries) {
                Log::info('[Playwright] Retry karena error retryable: ' . $errorMsg);
                sleep(10);
                return $this->login($email, $pin, $retryCount + 1);
            }
            
            return null;
        }

        Log::info('[Playwright] Login berhasil untuk: ' . ($result['store_name'] ?? $email));
        return $result;
    }

    /**
     * Eksekusi command dengan timeout menggunakan proc_open
     */
    private function executeWithTimeout(string $cmd, int $timeoutSeconds): ?array
    {
        $descriptorspec = [
            0 => ['pipe', 'r'], // stdin
            1 => ['pipe', 'w'], // stdout
            2 => ['pipe', 'w'], // stderr
        ];

        $process = proc_open($cmd, $descriptorspec, $pipes);
        
        if (!is_resource($process)) {
            return null;
        }

        // Set non-blocking mode
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $output = [];
        $startTime = time();
        
        while (true) {
            $elapsed = time() - $startTime;
            
            if ($elapsed > $timeoutSeconds) {
                // Timeout: terminate process
                proc_terminate($process, 9);
                proc_close($process);
                Log::error('[Playwright] Process timeout setelah ' . $timeoutSeconds . ' detik');
                return null;
            }
            
            // Baca stdout
            $stdout = stream_get_contents($pipes[1]);
            if ($stdout !== false && $stdout !== '') {
                $output[] = $stdout;
            }
            
            // Baca stderr
            $stderr = stream_get_contents($pipes[2]);
            if ($stderr !== false && $stderr !== '') {
                $output[] = $stderr;
            }
            
            // Cek status process
            $status = proc_get_status($process);
            if (!$status['running']) {
                // Process selesai, baca sisa output
                $remainingStdout = stream_get_contents($pipes[1]);
                if ($remainingStdout !== false && $remainingStdout !== '') {
                    $output[] = $remainingStdout;
                }
                $remainingStderr = stream_get_contents($pipes[2]);
                if ($remainingStderr !== false && $remainingStderr !== '') {
                    $output[] = $remainingStderr;
                }
                
                proc_close($process);
                
                if ($status['exitcode'] !== 0) {
                    Log::error('[Playwright] Process exit dengan code: ' . $status['exitcode']);
                }
                
                return $output;
            }
            
            usleep(100000); // Sleep 100ms
        }
    }

    /**
     * Login, simpan token + info pangkalan, dan return token.
     * Password terenkripsi di database.
     */
    public function loginAndSave(PangkalanSession $session): ?string
    {
        // Decrypt password dari database
        $password = $session->password;
        if (! $password) {
            Log::warning('[Playwright] Password tidak tersedia untuk: ' . $session->pangkalan_id);
            return null;
        }
        
        // Cek apakah password terenkripsi
        try {
            $decryptedPassword = Crypt::decryptString($password);
        } catch (\Exception $e) {
            // Mungkin password masih plaintext (migration diperlukan)
            Log::warning('[Playwright] Password tidak terenkripsi untuk: ' . $session->pangkalan_id . ', segera migrate');
            $decryptedPassword = $password;
        }

        $result = $this->login($session->username, $decryptedPassword);
        if (! $result) return null;

        $pangkalanId = $result['pangkalan_id'] ?? $session->pangkalan_id;

        // Update informasi pangkalan dari API
        if (isset($result['store_name']) && $result['store_name'] && ! $session->label) {
            $session->update(['label' => $result['store_name']]);
        }

        // Simpan registration_id (ID resmi pangkalan dari Pertamina)
        if (isset($result['registration_id']) && $result['registration_id']) {
            $session->update([
                'registration_id' => $result['registration_id'],
                'last_login_at'   => now(),
            ]);
        }

        // Validasi token sebelum simpan
        if (!$this->validateToken($result['token'])) {
            Log::error('[Playwright] Token tidak valid (expired atau malformed) untuk: ' . $pangkalanId);
            return null;
        }

        // Simpan token
        try {
            $tokenData = $this->decodeToken($result['token']);
            
            PangkalanToken::updateOrCreate(
                ['pangkalan_id' => $pangkalanId],
                [
                    'label'            => $result['store_name'] ?? $session->label,
                    'token'            => $result['token'],
                    'token_issued_at'  => $tokenData['issued_at'] ?? now(),
                    'token_expires_at' => $tokenData['expires_at'],
                    'is_active'        => true,
                ]
            );
            
            Log::info('[Playwright] Token berhasil disimpan untuk pangkalan_id: ' . $pangkalanId);
        } catch (\Exception $e) {
            Log::error('[Playwright] Gagal simpan token: ' . $e->getMessage());
        }

        return $result['token'];
    }
    
    /**
     * Validasi token JWT (cek format dan expired)
     */
    private function validateToken(string $token): bool
    {
        try {
            $parts = explode('.', $token);
            if (count($parts) !== 3) {
                return false;
            }
            
            $payload = $this->decodeToken($token);
            
            // Cek expired
            if (isset($payload['expires_at']) && $payload['expires_at'] < now()) {
                Log::warning('[Playwright] Token sudah expired pada: ' . $payload['expires_at']);
                return false;
            }
            
            return true;
        } catch (\Exception $e) {
            Log::error('[Playwright] Validasi token gagal: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Decode JWT token
     */
    private function decodeToken(string $token): array
    {
        $parts = explode('.', $token);
        $payloadB64 = $parts[1];
        // Add padding if needed
        $payloadB64 = str_pad(strtr($payloadB64, '-_', '+/'), strlen($payloadB64) + (4 - strlen($payloadB64) % 4) % 4, '=');
        $payload = json_decode(base64_decode($payloadB64), true);
        
        return [
            'sub'        => $payload['sub'] ?? null,
            'issued_at'  => isset($payload['iat']) ? Carbon::createFromTimestamp($payload['iat']) : null,
            'expires_at' => isset($payload['exp']) ? Carbon::createFromTimestamp($payload['exp']) : null,
        ];
    }
}