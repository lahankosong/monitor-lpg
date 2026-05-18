<?php

namespace App\Console\Commands;

use App\Models\PangkalanSession;
use Illuminate\Console\Command;

class CleanPendingSessions extends Command
{
    protected $signature   = 'akun:clean-pending {--force : Hapus tanpa konfirmasi}';
    protected $description = 'Hapus baris pending_ yang sudah punya pasangan UUID di pangkalan_sessions';

    public function handle(): int
    {
        // Cari username yang punya KEDUA baris pending_ dan UUID asli
        $duplikat = PangkalanSession::where('pangkalan_id', 'like', 'pending_%')
            ->get()
            ->filter(function ($pending) {
                // Ada baris lain dengan username yang sama dan bukan pending_
                return PangkalanSession::where('username', $pending->username)
                    ->where('pangkalan_id', 'not like', 'pending_%')
                    ->exists();
            });

        if ($duplikat->isEmpty()) {
            $this->info('Tidak ada duplikat pending_ yang perlu dibersihkan.');
            return 0;
        }

        $this->info("Ditemukan {$duplikat->count()} baris pending_ yang punya pasangan UUID:");
        foreach ($duplikat as $d) {
            $this->line("  - {$d->label} ({$d->username}) → {$d->pangkalan_id}");
        }

        if (!$this->option('force')) {
            if (!$this->confirm("Hapus {$duplikat->count()} baris pending_ ini?")) {
                $this->info('Dibatalkan.');
                return 0;
            }
        }

        $ids = $duplikat->pluck('id');
        PangkalanSession::whereIn('id', $ids)->delete();

        $this->info("✓ {$duplikat->count()} baris pending_ berhasil dihapus.");
        $this->line('Jalankan php artisan akun:clean-pending kapan saja jika muncul duplikat lagi.');

        return 0;
    }
}
