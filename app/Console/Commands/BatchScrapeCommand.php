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
        $accountPath = base_path('scripts/accounts.json');
        $resultFile  = storage_path('app/batch_result.json');

        if (! file_exists($scriptPath)) {
            $this->error("Script tidak ditemukan: {$scriptPath}");
            return 1;
        }

        if (! file_exists($accountPath)) {
            $this->error("accounts.json tidak ditemukan: {$accountPath}");
            return 1;
        }

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

        while (! feof($handle)) {
            // Cek flag stop dari dashboard setiap iterasi
            if (Cache::get('batch_scrape_stop', false)) {
                $this->warn("\n⏹ Dihentikan oleh user. Menutup proses...");
                pclose($handle);
                Cache::forget('batch_scrape_running');
                Cache::forget('batch_scrape_stop');
                return 0;
            }

            $line = fgets($handle, 4096);
            if ($line !== false) {
                $line = trim($line);
                if (! $line) continue;

                $this->line($line);

                // Update progress cache dari baris output Python
                if (preg_match('/\[(\d+)\/(\d+)\]\s+(.+?)(\s+\(|$)/', $line, $m)) {
                    Cache::put('batch_scrape_progress', [
                        'current' => (int) $m[1],
                        'total'   => (int) $m[2],
                        'label'   => trim($m[3]),
                    ], now()->addHours(2));

                    // Cek stop lagi saat mulai pangkalan baru
                    if (Cache::get('batch_scrape_stop', false)) {
                        $this->warn("\n⏹ Dihentikan setelah pangkalan " . $m[1]);
                        pclose($handle);
                        Cache::forget('batch_scrape_running');
                        Cache::forget('batch_scrape_stop');
                        return 0;
                    }
                }

                // Deteksi pangkalan yang gagal karena transaksi aktif — SKIP, jangan retry
                if (str_contains($line, 'GAGAL') && str_contains($line, 'None')) {
                    $this->warn("  → Pangkalan mungkin sedang aktif transaksi, dilewati (tidak retry)");
                }
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

        Cache::forget('batch_scrape_running');
        Cache::forget('batch_scrape_stop');
        return 0;
    }
}
