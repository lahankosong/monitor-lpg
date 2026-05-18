<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClearScrapeData extends Command
{
    protected $signature   = 'scrape:clear {--force : Hapus tanpa konfirmasi}';
    protected $description = 'Kosongkan semua data hasil scraping (transactions, violations, logs, summaries)';

    public function handle(): int
    {
        $tables = [
            'transactions'     => 'Data transaksi NIK',
            'nik_violations'   => 'Pelanggaran interval',
            'scrape_logs'      => 'Log scraping',
            'daily_summaries'  => 'Ringkasan harian',
        ];

        // Tampilkan jumlah data saat ini
        $this->info('Data saat ini:');
        $total = 0;
        foreach ($tables as $table => $label) {
            if (DB::getSchemaBuilder()->hasTable($table)) {
                $count = DB::table($table)->count();
                $total += $count;
                $this->line("  {$label}: " . number_format($count) . " baris");
            }
        }

        if ($total === 0) {
            $this->info('Semua tabel sudah kosong.');
            return 0;
        }

        // Konfirmasi
        if (!$this->option('force')) {
            if (!$this->confirm("Hapus total " . number_format($total) . " baris? Tindakan ini tidak bisa dibatalkan.")) {
                $this->info('Dibatalkan.');
                return 0;
            }
        }

        // Hapus dengan urutan yang benar (foreign key)
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach ($tables as $table => $label) {
            if (DB::getSchemaBuilder()->hasTable($table)) {
                DB::table($table)->truncate();
                $this->line("  ✓ {$label} dikosongkan");
            }
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->info('Selesai. Semua data scraping sudah dikosongkan.');
        $this->line('Data yang TIDAK dihapus: pangkalan_sessions, pangkalan_tokens, users, pangkalans, dan semua tabel agen.');

        return 0;
    }
}
