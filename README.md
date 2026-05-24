# Monitor LPG Subsidi — Laravel

Sistem pengawasan transaksi LPG subsidi per NIK Rumah Tangga berbasis Laravel + MySQL.
Mengambil data langsung dari API MyPertamina (`api-map.my-pertamina.id`).

---

## Persyaratan

- PHP >= 8.1
- Laravel 10 / 11
- MySQL 8.0+
- Composer
- Node.js (opsional, hanya jika pakai Vite)

---

## Instalasi

### 1. Buat project Laravel baru

```bash
composer create-project laravel/laravel monitor-lpg
cd monitor-lpg
```

### 2. Copy semua file dari paket ini

Salin file-file berikut ke project Laravel:

```
database/migrations/  → ke database/migrations/
app/Models/           → ke app/Models/
app/Jobs/             → ke app/Jobs/
app/Http/Controllers/ → ke app/Http/Controllers/
resources/views/      → ke resources/views/
routes/web.php        → ke routes/web.php
```

### 3. Konfigurasi .env

```env
APP_NAME="Monitor LPG Subsidi"
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=monitor_lpg
DB_USERNAME=root
DB_PASSWORD=

QUEUE_CONNECTION=database
```

### 4. Buat database

```bash
mysql -u root -e "CREATE DATABASE monitor_lpg CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

### 5. Jalankan migrasi

```bash
php artisan migrate
```

### 6. Setup queue (untuk job scraping background)

```bash
php artisan queue:table
php artisan migrate
php artisan queue:work --tries=3
```

### 7. Jalankan server

```bash
php artisan serve
```

Akses: http://localhost:8000/dashboard

---

## Cara Pakai

### Langkah 1 — Simpan Bearer Token

1. Buka `https://subsiditepatlpg.mypertamina.id/merchant-login`
2. Login dengan akun pangkalan
3. Tekan **F12** → tab **Network** → filter: `api-map`
4. Buka halaman **Rekap Penjualan** di aplikasi
5. Klik request ke `/general/v3/transactions/report`
6. Tab **Headers** → copy nilai `Authorization` (contoh: `eyJhbGci...`)
7. Di dashboard, klik **"+ Update Token"** → isi ID Pangkalan + paste token

> Token berlaku ±15 menit. Perlu diperbarui setiap sesi baru.

### Langkah 2 — Scrape Data

1. Klik tombol **"Scrape Data"** di navbar
2. Isi tanggal awal dan akhir
3. Klik **Jalankan** → job akan berjalan di background

### Langkah 3 — Monitor NIK

- **Dashboard** → lihat ringkasan status NIK + grafik harian
- **Monitor NIK** → tabel semua NIK dengan status dan pelanggaran interval
- Klik **Detail** pada NIK tertentu → lihat riwayat pembelian lengkap

---

## Logika Deteksi Pelanggaran

| Kondisi | Status |
|---------|--------|
| Hanya 1 transaksi | Data Baru |
| Jarak < 50% interval ATAU ≥3 pelanggaran | Frekuensi Tinggi (Alert) |
| Jarak < 75% interval ATAU ≥1 pelanggaran | Perlu Pantau (Warn) |
| Semua interval terpenuhi | Aman |

Default interval minimum: **7 hari** (dapat diubah via filter)

---

## Struktur Database

| Tabel | Isi |
|-------|-----|
| `transactions` | Semua data transaksi dari API |
| `daily_summaries` | Rekap harian (sold, modal, profit, gross) |
| `nik_violations` | Pelanggaran interval per NIK |
| `pangkalan_tokens` | Bearer token per pangkalan |
| `scrape_logs` | Log riwayat scraping |

---

## Tambahkan Model yang Hilang

Buat file `app/Models/DailySummary.php`:

```php
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class DailySummary extends Model {
    protected $fillable = ['pangkalan_id','summary_date','sold','modal','profit','gross'];
    protected $casts = ['summary_date' => 'date'];
}
```

Buat file `app/Models/ScrapeLog.php`:

```php
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ScrapeLog extends Model {
    protected $fillable = ['pangkalan_id','start_date','end_date','status',
                           'records_fetched','records_saved','error_message','scraped_at'];
    protected $casts = ['scraped_at' => 'datetime'];
}
```

Buat file `app/Models/PangkalanToken.php`:

```php
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class PangkalanToken extends Model {
    protected $fillable = ['pangkalan_id','label','token',
                           'token_issued_at','token_expires_at','is_active'];
    protected $casts = ['token_issued_at'=>'datetime','token_expires_at'=>'datetime','is_active'=>'boolean'];
}
```

---

## Opsional: Auto-Scrape Harian

Tambahkan di `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    // Scrape otomatis setiap hari jam 23:00
    $schedule->call(function () {
        \App\Jobs\ScrapeTransactionsJob::dispatch(
            today()->toDateString(),
            today()->toDateString(),
            'PKL-001' // sesuaikan ID pangkalan
        );
    })->dailyAt('23:00');
}
```

Jalankan scheduler:
```bash
php artisan schedule:run   # manual test
# atau tambahkan cron:
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```
# rawarun
