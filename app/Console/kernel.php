// ── Tambahkan ke app/Console/Kernel.php di method schedule() ──────

// Scrape otomatis 2x sehari (setelah GitHub Actions refresh token)
// jam 06:30 WIB — GitHub Actions jalan jam 06:00
$schedule->command('scrape:transaksi --from=' . now()->subDay()->toDateString())
         ->dailyAt('06:30')
         ->withoutOverlapping()
         ->runInBackground();

// jam 18:30 WIB — GitHub Actions jalan jam 18:00
$schedule->command('scrape:transaksi --from=' . now()->toDateString())
         ->dailyAt('18:30')
         ->withoutOverlapping()
         ->runInBackground();

// Cek token tiap jam — notif jika semua expired
$schedule->command('token:status')
         ->hourly()
         ->withoutOverlapping();
