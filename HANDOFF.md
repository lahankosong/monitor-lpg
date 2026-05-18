# LPG Monitor — Developer Handoff

Dokumen ini untuk **Claude Code / developer** yang akan menerapkan desain ini ke codebase Laravel di `C:\xampp\htdocs\monitor-lpg`.

> Baca juga `CLAUDE.md` di root project untuk konteks bisnis. Dokumen ini hanya membahas **tampilan & token desain**, bukan logika bisnis.

---

## 1. Apa yang dipindahkan

| Dari design system | Ke codebase Laravel |
|---|---|
| `colors_and_type.css` | `resources/css/lpg-tokens.css` (di-import di `app.css`) |
| Font Google CDN (Inter, Fraunces, Poppins) | Tetap CDN — atau host di `public/fonts/` |
| `ui_kits/admin/Layout.jsx` (Sidebar + Topbar) | `resources/views/layouts/app.blade.php` |
| `ui_kits/admin/Login.jsx` | `resources/views/auth/login.blade.php` |
| `ui_kits/admin/Dashboard.jsx` | `resources/views/Map/dashboard.blade.php` |
| `ui_kits/admin/NIK.jsx` | `resources/views/dashboard/nik.blade.php` |
| `ui_kits/admin/Operasional.jsx` | `resources/views/agen/operasional/kitir/index.blade.php`, `surat-jalan/index.blade.php` |
| `ui_kits/admin/Distribusi.jsx` | `resources/views/agen/distribusi/realisasi.blade.php`, `stok.blade.php` |
| `ui_kits/admin/Akuntansi.jsx` | `resources/views/agen/akuntansi/brimola/index.blade.php`, `audit.blade.php` |
| `ui_kits/admin/Database.jsx` | `resources/views/agen/database/{pangkalan,spbe,karyawan,armada}.blade.php` |
| `ui_kits/admin/Sistem.jsx` | `resources/views/dashboard/{batch-scrape,settings}.blade.php` |
| `ui_kits/driver/components.jsx` | `resources/views/agen/driver/{index,stok}.blade.php` + `layouts/driver.blade.php` |

---

## 2. Pondasi yang harus dipasang dulu

### 2.1. Drop token CSS

Salin `colors_and_type.css` ke `resources/css/lpg-tokens.css`. Lalu di `resources/css/app.css`:

```css
@import './lpg-tokens.css';
@import 'tailwindcss';

@source '../../vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php';
@source '../../storage/framework/views/*.php';
@source '../**/*.blade.php';
@source '../**/*.js';
```

Lalu jalankan `npm run build` (atau `composer dev` untuk dev mode).

### 2.2. Bersihkan layout lama

File `resources/views/layouts/app.blade.php` saat ini punya **blok `<style>` ~250 baris** yang mendefinisikan token CSS sendiri. **Hapus seluruh blok itu** — semua token sudah ada di `lpg-tokens.css`. Sisakan hanya markup struktur (topbar, sidebar, content).

### 2.3. Update sidebar partial

`resources/views/layouts/partials/sidebar.blade.php` perlu disesuaikan dengan urutan & icon baru. Lihat array `NAV` di `ui_kits/admin/Layout.jsx` sebagai sumber kebenaran:

```
Monitor MAP
├── Dashboard         → dashboard.index
├── Monitor NIK       → dashboard.nik.list   (badge: count(nik_violations))
└── Scrape Data       → dashboard.batch.index

Operasional
├── Kitir             → dashboard.agen.operasional.kitir.index
└── Surat Jalan       → dashboard.agen.operasional.sj.index (badge: count SJ aktif)

Distribusi
├── Realisasi         → dashboard.agen.distribusi.index
└── Stok & Gudang     → dashboard.agen.distribusi.stok

Akuntansi
├── BRImola           → dashboard.agen.akuntansi.brimola.index
└── Audit FIFO        → dashboard.agen.akuntansi.brimola.audit

Database
├── Pangkalan / SPBE / Karyawan / Armada (sudah ada)

Sistem
├── Pengaturan        → dashboard.settings.index (BUAT BARU)
└── Keluar            → logout
```

---

## 3. Konvensi spec dari design system

Sesuai catatan di `CLAUDE.md`, codebase pakai **inline style + CSS variables**, bukan class Tailwind di views agen. Ini sudah selaras dengan `lpg-tokens.css`. Aturan:

### Variabel yang BOLEH dipakai
```css
var(--bg)                /* halaman canvas */
var(--surface)           /* card background */
var(--surface-sunken)    /* zebra / header */
var(--border)            /* hairline */
var(--border-strong)     /* tombol/input border */
var(--text)              /* default */
var(--text-muted)        /* label, helper */
var(--text-light)        /* placeholder */

var(--accent)            /* champagne — CTA utama */
var(--accent-light)      /* tint bg */
var(--accent-dark)       /* hover */
var(--accent-fg)         /* teks di atas accent */

var(--success) / --success-bg / --success-text
var(--warning) / --warning-bg / --warning-text
var(--danger)  / --danger-bg  / --danger-text
var(--info)    / --info-bg    / --info-text

var(--cat-rt) / --cat-rt-bg          /* Rumah Tangga chip */
var(--cat-um) / --cat-um-bg          /* Usaha Mikro chip */
var(--cat-pengecer) / --cat-pengecer-bg

var(--radius-sm) /* 4px - chip */
var(--radius-md) /* 6px - button, input, card */
var(--radius-lg) /* 8px - large card */
var(--radius-xl) /* 12px - modal */
var(--radius-pill) /* 9999px */

var(--shadow-xs) /* table row */
var(--shadow-sm) /* card */
var(--shadow-md) /* hover */
var(--shadow-lg) /* dropdown, popover */
var(--shadow-modal) /* dialog */

var(--font-sans)    /* 'Inter' — UI */
var(--font-display) /* 'Fraunces' — angka besar, title hero */
var(--font-mono)    /* IDs, kode, log */
```

### Pola komponen baku

**Button primary:**
```blade
<button style="background:var(--text);color:var(--bg);border:1px solid var(--text);
                border-radius:var(--radius-md);padding:8px 16px;font-size:13px;
                font-weight:500;cursor:pointer;letter-spacing:0.01em">
  Cari
</button>
```

**Button accent (CTA utama / champagne):**
```blade
<button style="background:var(--accent);color:var(--accent-fg);border:1px solid var(--accent);
                border-radius:var(--radius-md);padding:8px 16px;font-size:13px;
                font-weight:600;cursor:pointer;letter-spacing:0.01em">
  Simpan
</button>
```

**Card:**
```blade
<div style="background:var(--surface);border:1px solid var(--border);
            border-radius:var(--radius-md);box-shadow:var(--shadow-xs);overflow:hidden">
  <div style="padding:14px 20px;border-bottom:1px solid var(--border)">
    <h2 style="font-size:14px;font-weight:600;margin:0">Title</h2>
  </div>
  <div style="padding:20px">…</div>
</div>
```

**Stat card (dashboard):**
```blade
<div style="background:var(--surface);border:1px solid var(--border);
            border-top:2px solid var(--accent);border-radius:var(--radius-md);
            padding:14px 18px 16px;box-shadow:var(--shadow-xs)">
  <div style="font-size:10px;font-weight:600;letter-spacing:0.22em;
              text-transform:uppercase;color:var(--text-light)">PANGKALAN</div>
  <div style="font-family:var(--font-display);font-size:26px;font-weight:600;
              letter-spacing:-0.02em;line-height:1;margin-top:10px">142</div>
</div>
```

**Pill (status):**
```blade
<span style="display:inline-flex;align-items:center;gap:6px;background:var(--success-bg);
             color:var(--success-text);font-size:11px;font-weight:600;
             padding:3px 10px;border-radius:9999px">
  <span style="width:5px;height:5px;border-radius:50%;background:var(--success)"></span>
  Aman
</span>
```

**Eyebrow (label kecil):**
```blade
<div style="font-size:10px;font-weight:600;letter-spacing:0.22em;
            text-transform:uppercase;color:var(--text-light)">Eyebrow</div>
```

**Page header:**
```blade
<div style="display:flex;justify-content:space-between;align-items:flex-end;
            gap:16px;margin-bottom:24px;padding-bottom:18px;border-bottom:1px solid var(--border)">
  <div>
    <div style="font-size:10px;font-weight:600;letter-spacing:0.22em;
                text-transform:uppercase;color:var(--accent);margin-bottom:6px">Eyebrow</div>
    <h1 style="font-family:var(--font-display);font-size:28px;font-weight:600;
                margin:0;letter-spacing:-0.02em;line-height:1.1">Dashboard MAP</h1>
    <p style="font-size:13px;color:var(--text-muted);margin-top:6px;max-width:60ch">
      Subtitle penjelasan singkat.
    </p>
  </div>
  <div style="display:flex;gap:8px">…tombol…</div>
</div>
```

**Tabel:**
```blade
<table style="width:100%;border-collapse:collapse;font-size:13px">
  <thead>
    <tr>
      <th style="padding:10px 16px;text-align:left;font-size:10px;font-weight:600;
                  color:var(--text-light);text-transform:uppercase;letter-spacing:0.18em;
                  border-bottom:1px solid var(--border);background:var(--surface-sunken)">
        Nama
      </th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td style="padding:11px 16px;border-bottom:1px solid var(--border-light)">…</td>
    </tr>
  </tbody>
</table>
```

Untuk row dengan pelanggaran, tambahkan `background:var(--danger-bg)` di `<tr>`.

---

## 4. Mode Driver — perbedaan

Layout `driver.blade.php` **tidak boleh ikut theme** admin. Kunci ke palet dark dengan menambahkan class `.driver-surface` di `<body>` atau elemen root. Token akan otomatis re-bind ke obsidian + champagne.

Bottom nav floating pill:
```blade
<nav style="position:fixed;left:16px;right:16px;bottom:14px;
            display:flex;padding:4px;
            background:rgba(20,26,35,0.85);backdrop-filter:blur(16px);
            border:1px solid rgba(201,168,106,0.20);
            border-radius:9999px;
            box-shadow:0 12px 40px rgba(0,0,0,0.5)">
  …
</nav>
```

Pangkalan row di Surat Jalan: lihat `ui_kits/driver/components.jsx` `PangkalanRow` — angka pakai `var(--font-display)`, label pakai eyebrow caps.

---

## 5. Migrasi inkremental (rekomendasi urutan)

Saran urutan supaya tidak meledak sekaligus:

1. **Drop `lpg-tokens.css` dulu** (sudah dipakai semua views via CSS variables) — tampilan langsung berubah palet tanpa harus ubah markup.
2. **Login** — file standalone, paling aman direfactor pertama.
3. **Layout app.blade.php + sidebar partial** — ganti markup topbar, sidebar, struktur layout. Setelah ini, semua sub-view langsung dapat shell baru.
4. **Dashboard MAP** — paling visible, jadi prioritas.
5. **Monitor NIK** — query sudah ada, hanya markup table yang perlu refactor.
6. **Surat Jalan + Realisasi** — alur bisnis terpenting; pakai card per SJ seperti di `Operasional.jsx`.
7. **BRImola + Audit** — bisa belakangan, low traffic.
8. **Pangkalan / SPBE / Karyawan / Armada** — CRUD biasa.
9. **Driver layout** — terakhir, karena testing field perlu device beneran.

---

## 6. Hal yang BELUM ada di design system

Berikut yang masih perlu didesain saat developer butuh:

- **Form tambah/edit Pangkalan** (modal multi-step?) — saat ini cuma list + detail readonly.
- **Halaman tebusan** terpisah dari Kitir (CLAUDE.md menyebutkan ini sebagai langkah berbeda dalam alur Kitir → Tebusan → SJ).
- **Pengalihan tabung antar pangkalan** (`sj_pengalihan`) — UI khusus.
- **Tambahan qty dari sumber lain** (`sj_detail_tambahan`) — UI khusus.
- **Laporan PDF / cetak BAP** (Berita Acara Penyerahan).
- **Wizard scraping ulang** untuk pangkalan tertentu (manual override).
- **Notifikasi pusat** (saat ini icon bell di topbar masih placeholder).

Minta saya untuk mendesain ini saat Anda butuh — tinggal beri tahu prioritasnya.

---

## 7. Aturan UI yang harus DIINGAT developer

1. **Jangan invent warna baru.** Selalu pakai token. Kalau butuh warna yang belum ada, minta saya menambahkan ke `lpg-tokens.css`.
2. **Jangan pakai gradient kecuali untuk header SJ driver.** Editorial = solid + texture, bukan gradient SaaS.
3. **Angka besar selalu pakai `var(--font-display)`** (Fraunces). Body selalu Inter. ID/kode selalu mono.
4. **Eyebrow caps 10px / weight 600 / tracking 0.22em.** Itu sidik jari visual brand.
5. **Border-radius maksimal 8px** untuk card biasa, 12px untuk modal. Tidak pernah lebih.
6. **Sentence case** untuk semua kecuali eyebrow & table header (UPPERCASE).
7. **Sidebar selalu dark** (obsidian) — bahkan di theme light. Itu fitur, bukan bug.
8. **Hover button** = darken background, bukan transform/shadow.
9. **Status pakai dot pill**, bukan emoji.
10. **Empty state pakai `<EmptyState>` pattern** — icon + judul Fraunces + subtitle muted.

---

## 8. Pertanyaan/Discussion buat saya (Claude design)

Kalau Anda atau developer butuh saya:

- **Mendesain halaman baru** → kirim deskripsi alur + screenshot view lama → saya buat versi editorial.
- **Mendesain form panjang** → kirim daftar field → saya buat multi-step layout.
- **Print/PDF layout** → kirim contoh dokumen Berita Acara existing → saya buat versi cetak.
- **Mockup laporan untuk Direktur** → kirim data/metrik yang ditampilkan → saya buat dashboard executive.

Cukup buka project ini lagi dan ngobrol.

---

---

## 9. Multi-cabang / multi-tenant

Per keputusan 18 Mei 2026, sistem berkembang ke **multi-cabang dengan database-per-tenant**. Spec lengkap arsitektur ada di **`CLAUDE-multitenant.md`** di root project. Ringkasannya:

- **Strategi DB**: database-per-tenant (`monitor_lpg_karawang`, `monitor_lpg_bandung`, dst).
- **DB pusat** baru: `central_admin` — berisi `tenants`, `tenant_users`, `role_templates`, `audit_log`.
- **User terikat 1 cabang** lewat `tenant_users.tenant_id`. Super-admin punya `tenant_id = NULL`.
- **Cabang baru** = clone master dari cabang induk (pangkalan, SPBE, harga), transaksi kosong.
- **Super-admin** punya shell terpisah ("Holding Console") — tidak melihat transaksi langsung, hanya cabang/user/role/audit. Untuk lihat data operasional cabang, harus **impersonate**.

### UI yang baru ditambahkan

| File | Apa isinya |
|---|---|
| `ui_kits/admin/mockData.jsx` | `TENANTS`, `USERS_ALL`, `ROLES`, `PRIVILEGE_MATRIX`, `AUDIT_LOG` |
| `ui_kits/admin/SuperAdmin.jsx` | 6 page: Ringkasan, Cabang, Pengguna, Role &amp; Privilege, Audit Log, Pengaturan Holding |
| `ui_kits/admin/CabangWizard.jsx` | Wizard 4-langkah: Profil → Cabang Induk → Admin Awal → Konfirmasi + bootstrap progress |
| `ui_kits/admin/Layout.jsx` | + `TenantBadge` di sidebar, + `ImpersonateBanner` di topbar, + dukung `nav` prop |
| `ui_kits/admin/Login.jsx` | Deteksi `sa@…` → routing ke Holding Console |

### Penambahan kerja di Laravel

1. **Buat connection `central` & `template`** di `config/database.php`:
   ```php
   'central'  => [...same as mysql, 'database' => env('DB_DATABASE','central_admin')],
   'template' => [...'database' => 'monitor_lpg_template'],
   'tenant'   => [...'database' => null], // di-set di runtime via TenantManager
   ```

2. **Buat 4 model di DB pusat**:
   - `App\Models\Central\Tenant` — `protected $connection = 'central'`
   - `App\Models\Central\TenantUser`
   - `App\Models\Central\RoleTemplate`
   - `App\Models\Central\AuditLog`

3. **Tambah `TenantManager` service** + middleware `ResolveTenant` (lihat `CLAUDE-multitenant.md` §3).

4. **Update `Authenticate` middleware**: setelah login OK, panggil `TenantManager::setCurrent($user->tenant_id)` kecuali user adalah super-admin.

5. **Migration baru**: untuk schema `tenants`, `tenant_users`, `role_templates`, `audit_log`. Plus migrate `users` lama dari `monitor_lpg` ke `tenant_users` dengan `tenant_id = 'tnt_krw'`.

6. **Routes baru**:
   ```php
   // routes/web.php
   Route::middleware(['auth','role:super_admin'])->prefix('holding')->name('holding.')->group(function () {
       Route::get('/',         [HoldingController::class, 'overview'])->name('overview');
       Route::resource('cabang', TenantController::class);
       Route::resource('users',  HoldingUserController::class);
       Route::get('roles',       [RoleController::class, 'matrix'])->name('roles');
       Route::post('roles/save', [RoleController::class, 'save'])->name('roles.save');
       Route::get('audit',       [AuditController::class, 'index'])->name('audit');
       Route::post('impersonate/{user}', [ImpersonateController::class, 'start']);
       Route::post('impersonate/end',     [ImpersonateController::class, 'end']);
   });
   ```

7. **Job `BootstrapTenantJob`** sesuai flow di `CLAUDE-multitenant.md` §"Onboarding cabang baru".

8. **Privilege check** di setiap controller / blade — gunakan helper:
   ```php
   // app/Helpers/can.php
   function lpg_can(string $menu): bool {
       $user = auth()->user();
       if ($user->role === 'super_admin') return true;
       $defaults = config('lpg.role_defaults')[$user->role] ?? [];
       $override = $user->privileges ?? [];
       return $override[$menu] ?? $defaults[$menu] ?? false;
   }
   ```

   Di Blade:
   ```blade
   @if(lpg_can('menu.brimola'))
     <a href="...">BRImola</a>
   @endif
   ```

9. **Audit log writer** — gunakan event Eloquent + observer untuk auto-log:
   ```php
   AuditLog::write('tenant.create',  $tenant, ['kode' => $tenant->kode]);
   AuditLog::write('login.success',  $user);
   AuditLog::write('impersonate.start', $targetUser);
   ```

### Urutan migrasi (multi-tenant) — rekomendasi

1. Buat `central_admin` + 4 tabel pusat, deploy tanpa pakai mereka dulu.
2. Migrasi `users` existing ke `tenant_users` (script artisan custom). Set `tenant_id = KRW`.
3. Aktifkan `ResolveTenant` middleware. Test login direktur Karawang masih jalan.
4. Build `Holding Console` Blade dari mock di `SuperAdmin.jsx`. **Mulai dari "Ringkasan" + "Cabang list"** dulu (read-only).
5. Tambahkan tombol "Tambah Cabang" + wizard `CabangWizard.jsx` (sudah ada mock UI). Backend: `BootstrapTenantJob` dengan dry-run dulu (tidak benar-benar create DB).
6. Setelah dry-run aman, aktifkan create DB beneran. Lakukan di staging dulu.
7. Implementasi `impersonate.start/end` + banner kuning.
8. Privilege matrix editor + helper `lpg_can()`.
9. Audit log viewer.
10. Pengaturan holding (password policy, retensi).

### Hal yang BELUM didesain untuk multi-tenant

Ini yang masih perlu didesain saat dibutuhkan:

- **Halaman profil cabang** (super-admin lihat detail 1 cabang) — sekarang baru list saja.
- **Halaman edit cabang** (suspend, ubah PIC, ubah identitas).
- **Halaman bootstrap status** (progress live saat job berjalan, retry kalau gagal).
- **Halaman re-sync master dari induk** (kalau pangkalan referensi di induk berubah, kapan/bagaimana mempropagasi ke cabang anak?).
- **Form tambah user** lintas cabang (modal di Holding Pengguna).
- **2FA enrollment & verifikasi**.
- **Dashboard direktur cabang** (terpisah dari dashboard admin — fokus KPI).

Minta saya untuk mendesain ini saat developer butuh — UI primitive sudah lengkap, tinggal komposisi baru.

### Aturan UI khusus multi-cabang

1. **Badge cabang aktif** wajib tampil di sidebar saat user login (sudah ada `TenantBadge`). Direktur tidak boleh sampai bingung sedang lihat data cabang mana.
2. **Impersonate banner** wajib full-width kuning saat aktif (sudah ada `ImpersonateBanner`).
3. **Super-admin sidebar** beda warna highlight (champagne lebih tebal) supaya beda dengan tenant shell.
4. **Confirm dialog** wajib untuk: hapus user, suspend cabang, mulai impersonate, reset matrix privilege.
5. **Audit log entry** untuk SEMUA aksi sensitif. Tidak boleh ada tombol yang tidak nge-log.

---

**Selesai.** Selamat memindahkan ke production. 🍷
