<?php

namespace App\Console\Commands;

use App\Models\PangkalanToken;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Cek status semua token — berapa yang aktif, berapa yang expired
 * php artisan token:status
 */
class TokenStatusCommand extends Command
{
    protected $signature   = 'token:status';
    protected $description = 'Tampilkan status token MyPertamina yang tersimpan';

    public function handle(): int
    {
        $tokens = PangkalanToken::orderBy('token_expires_at')->get();

        if ($tokens->isEmpty()) {
            $this->warn('Tidak ada token di database. Jalankan GitHub Actions untuk mendapatkan token.');
            return 1;
        }

        $rows = $tokens->map(function ($t) {
            // Bandingkan dalam UTC agar konsisten dengan nilai tersimpan
            $expired  = $t->token_expires_at
                ? $t->token_expires_at->utc()->isPast()
                : true;
            $sisa     = $t->token_expires_at
                ? ($expired ? 'EXPIRED' : $t->token_expires_at->utc()->diffForHumans())
                : '?';
            return [
                $t->label ?? $t->pangkalan_id,
                $t->token_expires_at?->format('d/m/Y H:i') ?? '—',
                $expired ? '❌ Expired' : '✅ Aktif',
                $sisa,
            ];
        })->toArray();

        $this->table(
            ['Pangkalan', 'Berlaku s/d', 'Status', 'Sisa'],
            $rows
        );

        $aktif   = $tokens->filter(fn($t) => $t->token_expires_at && !$t->token_expires_at->utc()->isPast())->count();
        $expired = $tokens->count() - $aktif;

        $this->line('');
        $this->info("Token aktif : {$aktif}");
        if ($expired > 0) {
            $this->warn("Token expired: {$expired} — perlu refresh via GitHub Actions");
            $this->line('  Jalankan: gh workflow run scrape-tokens.yaml');
        }

        // Estimasi waktu scraping
        if ($aktif > 0) {
            $estimasi = $aktif * 2; // ~2 detik per pangkalan (HTTP saja)
            $this->line("Estimasi waktu scraping {$aktif} pangkalan: ~{$estimasi} detik");
        }

        return 0;
    }
}
