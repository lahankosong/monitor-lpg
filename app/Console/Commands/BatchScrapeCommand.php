<?php

namespace App\Console\Commands;

use App\Http\Controllers\BatchScrapeController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BatchScrapeCommand extends Command
{
    protected $signature   = 'batch:scrape {--from= : Tanggal mulai YYYY-MM-DD} {--to= : Tanggal akhir YYYY-MM-DD}';
    protected $description = 'Batch scraping semua pangkalan via Playwright';

    public function handle(): int
    {
        $from = $this->option('from') ?? now()->startOfWeek()->toDateString();
        $to   = $this->option('to')   ?? now()->toDateString();

        $this->info("[BatchScrape] Mulai: {$from} s/d {$to}");

        $pythonPath  = env('PYTHON_PATH', 'python');
        $scriptPath  = base_path('scripts/auto_login_batch.py');
        $resultFile  = storage_path('app/batch_result.json');

        if (! file_exists($scriptPath)) {
            $this->error("Script tidak ditemukan: {$scriptPath}");
            return 1;
        }

        // Ambil credentials dari database (bukan accounts.json)
        $accountPath = storage_path('app/batch_accounts_temp.json');
        $sessions = \App\Models\PangkalanSession::where('is_active', true)
            ->whereNotNull('password_encrypted')
            ->get()
            ->groupBy('username') // deduplikasi
            ->map(fn($g) => $g->first(fn($s) => !str_starts_with($s->pangkalan_id, 'pending_')) ?? $g->first())
            ->values();

        $accounts = $sessions->map(function ($s) {
            try {
                $pin = \Illuminate\Support\Facades\Crypt::decryptString($s->password_encrypted);
                return ['label' => $s->label, 'email' => $s->username, 'pin' => $pin];
            } catch (\Exception $e) {
                return null;
            }
        })->filter()->values()->toArray();

        if (empty($accounts)) {
            $this->error('Tidak ada akun aktif di database.');
            return 1;
        }

        file_put_contents($accountPath, json_encode($accounts, JSON_UNESCAPED_UNICODE));
        $this->info('Menggunakan ' . count($accounts) . ' akun dari database.');

        if (file_exists($resultFile)) unlink($resultFile);

        // Reset flag stop sebelum mulai
        Cache::forget('batch_scrape_stop');
        Cache::put('batch_scrape_running', true, now()->addHours(2));

        set_time_limit(0);

        $cmd = sprintf(
            'set PYTHONUNBUFFERED=1 && %s %s --accounts %s --from %s --to %s --output %s',
            escapeshellcmd($pythonPath),
            escapeshellarg($scriptPath),
            escapeshellarg($accountPath),
            escapeshellarg($from),
            escapeshellarg($to),
            escapeshellarg($resultFile),
        );

        $this->info("Menjalankan Playwright...");
        $this->info("Ketik Ctrl+C atau klik Stop di dashboard untuk menghentikan.");

        $handle = popen($cmd . ' 2>&1', 'r');

        if (! $handle) {
            $this->error("Gagal menjalankan Python");
            Cache::forget('batch_scrape_running');
            return 1;
        }

        // File log realtime — Python tulis, PHP baca tiap detik
        $logFile = storage_path('app/batch_realtime_log.jsonl');
        if (file_exists($logFile)) unlink($logFile);
        Cache::put('batch_scrape_logs', [], now()->addHours(2));
        Cache::put('batch_log_file', $logFile, now()->addHours(2));

        $currentLabel = '';
        $currentIdx   = 0;
        $totalPangk   = 0;
        $lastFilePos  = 0;
        $lastCacheUpdate = 0;

        while (! feof($handle)) {
            if (Cache::get('batch_scrape_stop', false)) {
                $this->warn("\n⏹ Dihentikan oleh user.");
                pclose($handle);
                Cache::forget('batch_scrape_running');
                Cache::forget('batch_scrape_stop');
                return 0;
            }

            $line = fgets($handle, 4096);
            if ($line === false) {
                usleep(100000); // 100ms
                continue;
            }
            $line = trim($line);
            if (! $line) continue;

            $this->line($line);

            // ── Helper: tulis ke file log realtime ────────────────────
            $addLog = function (string $text, string $type = 'info') use ($logFile) {
                $entry = json_encode([
                    'time' => now()->format('H:i:s'),
                    'text' => $text,
                    'type' => $type,
                ], JSON_UNESCAPED_UNICODE) . "\n";
                file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
            };

            // ── Parse baris output Python ─────────────────────────────

            // [1/47] NAMA (email@...)
            if (preg_match('/\[(\d+)\/(\d+)\]\s+(.+?)\s*\((.+?)\)/', $line, $m)) {
                $currentIdx   = (int) $m[1];
                $totalPangk   = (int) $m[2];
                $currentLabel = trim($m[3]);
                $email        = trim($m[4]);

                Cache::put('batch_scrape_progress', [
                    'current' => $currentIdx,
                    'total'   => $totalPangk,
                    'label'   => $currentLabel,
                ], now()->addHours(2));

                $addLog("── [{$currentIdx}/{$totalPangk}] {$currentLabel} ({$email})", 'info');

                if (Cache::get('batch_scrape_stop', false)) {
                    pclose($handle);
                    Cache::forget('batch_scrape_running');
                    Cache::forget('batch_scrape_stop');
                    return 0;
                }
                continue;
            }

            // Membuka halaman login
            if (str_contains($line, 'Membuka halaman login')) {
                $addLog("  → Membuka halaman login MyPertamina...", 'step');
                continue;
            }

            // Input email
            if (str_contains($line, 'Input email')) {
                $addLog("  ✓ Input email", 'step');
                continue;
            }

            // Input PIN
            if (str_contains($line, 'Input PIN')) {
                $addLog("  ✓ Input PIN", 'step');
                continue;
            }

            // Klik login
            if (str_contains($line, 'Klik login')) {
                $addLog("  ✓ Klik login, menunggu token...", 'step');
                continue;
            }

            // Token berhasil
            if (str_contains($line, 'Token berhasil didapat') || str_contains($line, 'OK:')) {
                $addLog("  ✓ Token berhasil — login sukses", 'ok');
                continue;
            }

            // Info stok / store name
            if (preg_match('/OK:\s+(.+)/', $line, $m)) {
                $addLog("  ✓ Masuk sebagai: " . trim($m[1]), 'ok');
                continue;
            }

            // Transaksi ditemukan
            if (preg_match('/(\d+) transaksi \((.+?) s\/d (.+?)\)/', $line, $m)) {
                $addLog("  ✓ Scraping: {$m[1]} transaksi ({$m[2]} s/d {$m[3]})", 'ok');
                continue;
            }

            // GAGAL / error
            if (str_contains($line, 'GAGAL') || str_contains($line, 'Gagal') || str_contains($line, '✗')) {
                // Tentukan penyebab kegagalan
                $cause = 'error tidak diketahui';
                if (str_contains($line, 'Token tidak')) $cause = 'token tidak tertangkap — kemungkinan reCAPTCHA';
                elseif (str_contains($line, 'Browser error')) $cause = 'browser error';
                elseif (str_contains($line, 'timeout')) $cause = 'timeout';
                elseif (str_contains($line, 'credential')) $cause = 'email/PIN salah';
                elseif (str_contains($line, 'password')) $cause = 'password salah';
                elseif (str_contains($line, 'None')) $cause = 'pangkalan mungkin aktif transaksi';

                $addLog("  ✗ GAGAL: {$cause}", 'fail');
                continue;
            }

            // SELESAI semua
            if (str_contains($line, 'SELESAI')) {
                $addLog("━━ {$line} ━━", 'info');
                continue;
            }
        }
        pclose($handle);

        // Cek file hasil
        if (! file_exists($resultFile)) {
            $this->error("File hasil tidak ada: {$resultFile}");
            Cache::forget('batch_scrape_running');
            return 1;
        }

        $content     = file_get_contents($resultFile);
        $batchResult = json_decode($content, true);

        if (! $batchResult || ! isset($batchResult['results'])) {
            $this->error("File hasil tidak valid.");
            $this->line(substr($content, 0, 300));
            Cache::forget('batch_scrape_running');
            return 1;
        }

        $this->info("\nLogin batch selesai. Mengimport ke database...");

        try {
            $controller = app(BatchScrapeController::class);
            $stats      = $controller->importResult($resultFile);

            $this->info("✓ Berhasil    : {$stats['berhasil']} pangkalan");
            $this->info("✗ Gagal/Skip  : {$stats['gagal']} pangkalan");
            $this->info("+ Txn baru    : {$stats['total_baru']}");

            Log::info("[BatchScrape] Selesai", $stats);
        } catch (\Exception $e) {
            $this->error("Import gagal: " . $e->getMessage());
            Cache::forget('batch_scrape_running');
            return 1;
        }

        // Hapus temp accounts file
        if (file_exists($accountPath)) unlink($accountPath);

        Cache::forget('batch_scrape_running');
        Cache::forget('batch_scrape_stop');
        return 0;
    }
}
