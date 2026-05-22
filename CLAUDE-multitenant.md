# Multi-Cabang Architecture — LPG Monitor

Dokumen tambahan untuk `CLAUDE.md`. Ditulis 18 Mei 2026, setelah keputusan arsitektur multi-cabang.

> Sumber kebenaran arsitektur. Baca **sebelum** menambahkan tabel, route, atau view yang menyentuh data per-cabang.

---

## Ringkasan keputusan

| Keputusan | Pilihan | Alasan |
|---|---|---|
| **Strategi DB** | Database-per-tenant | Audit Pertamina sering minta data per agen; isolasi total; backup per cabang mudah |
| **Bootstrap cabang baru** | Clone master dari cabang induk, transaksi dikosongkan | Reuse data referensi (pangkalan, SPBE, harga, role) tanpa harus rebuild dari nol |
| **User ↔ cabang** | 1-to-1 (user terikat 1 cabang) | Sederhana, role per cabang, tidak ada bingung context-switching |
| **Super-admin** | Role khusus tanpa `tenant_id` | Atur cabang & privilege; tidak melihat transaksi cabang kecuali masuk via "impersonate" |

---

## Diagram

```
                ┌─────────────────────────┐
                │  central_admin (DB pusat) │
                │   - tenants               │
                │   - tenant_users          │
                │   - role_templates        │
                │   - audit_log             │
                └────────────┬──────────────┘
                             │ point to
              ┌──────────────┼──────────────┐
              │              │              │
   ┌──────────▼────┐ ┌───────▼────────┐ ┌───▼──────────┐
   │ monitor_lpg_  │ │ monitor_lpg_   │ │ monitor_lpg_ │
   │  karawang     │ │  bandung       │ │  template    │
   │               │ │                │ │  (READ-ONLY) │
   │ - pangkalans  │ │ - pangkalans   │ │ - schema     │
   │ - transactions│ │ - transactions │ │ - seed master│
   │ - users       │ │ - users        │ │ - kosong     │
   └───────────────┘ └────────────────┘ └──────────────┘
```

## Tabel di DB pusat (`central_admin`)

```sql
-- 1. Cabang
CREATE TABLE tenants (
    id              CHAR(26) PRIMARY KEY,    -- ulid
    kode            VARCHAR(16) UNIQUE,      -- 'KRW', 'BDG', 'BKS'
    nama            VARCHAR(120),            -- 'PT Rawarun Tech Energi — Karawang'
    db_name         VARCHAR(64) UNIQUE,      -- 'monitor_lpg_karawang'
    parent_id       CHAR(26) NULL,           -- cabang induk untuk cloning
    wilayah         VARCHAR(120),            -- 'Karawang, Cikampek'
    alamat          TEXT,
    siup            VARCHAR(64),
    npwp            VARCHAR(32),
    no_telepon      VARCHAR(32),
    email           VARCHAR(120),
    pic_nama        VARCHAR(80),             -- Direktur cabang
    pic_no_hp       VARCHAR(32),
    status          ENUM('aktif','draft','suspended','tutup') DEFAULT 'draft',
    bootstrap_state ENUM('pending','seeding','ready','failed') DEFAULT 'pending',
    bootstrap_log   JSON NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by      CHAR(26) NULL,
    activated_at    TIMESTAMP NULL,
    INDEX (status), INDEX (parent_id)
);

-- 2. User global — central auth, tenant lookup
CREATE TABLE tenant_users (
    id            CHAR(26) PRIMARY KEY,
    tenant_id     CHAR(26) NULL,             -- NULL untuk super-admin
    email         VARCHAR(120) UNIQUE,
    nama          VARCHAR(120),
    password      VARCHAR(255),              -- bcrypt
    role          VARCHAR(32),               -- 'super_admin' | 'direktur' | 'manajer' | 'admin' | 'driver'
    privileges    JSON NULL,                 -- override role default jika ada
    status        ENUM('aktif','nonaktif','suspended') DEFAULT 'aktif',
    last_login_at TIMESTAMP NULL,
    last_login_ip VARCHAR(45) NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (tenant_id), INDEX (role), INDEX (status),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
);

-- 3. Role template — definisi privilege per role
CREATE TABLE role_templates (
    id          CHAR(26) PRIMARY KEY,
    role        VARCHAR(32) UNIQUE,          -- 'direktur', 'manajer', dll
    nama        VARCHAR(80),
    deskripsi   TEXT,
    privileges  JSON,                        -- { "menu.dashboard": true, "menu.brimola": false, ... }
    is_system   BOOLEAN DEFAULT FALSE,       -- role bawaan tidak bisa dihapus
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 4. Audit log lintas cabang
CREATE TABLE audit_log (
    id          BIGINT AUTO_INCREMENT PRIMARY KEY,
    actor_id    CHAR(26) NULL,
    actor_email VARCHAR(120),
    tenant_id   CHAR(26) NULL,
    action      VARCHAR(64),                 -- 'tenant.create', 'user.privilege.change', 'login.success', 'impersonate.start'
    target_type VARCHAR(64) NULL,
    target_id   CHAR(64) NULL,
    payload     JSON NULL,
    ip          VARCHAR(45),
    ua          TEXT,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (tenant_id), INDEX (action), INDEX (created_at)
);
```

## Tabel di DB per-cabang (template)

Sama dengan schema sekarang — `transactions`, `pangkalans`, `surat_jalan_headers`, dst. Plus:

```sql
-- Tambahkan ke setiap tabel master untuk audit trail
ALTER TABLE pangkalans       ADD COLUMN created_by_user_email VARCHAR(120) NULL AFTER created_at;
ALTER TABLE surat_jalan_headers ADD COLUMN created_by_user_email VARCHAR(120) NULL AFTER created_at;
-- dst untuk semua tabel transaksi
```

`users` table per-cabang **tetap ada** tapi sebagai cache — sumber kebenaran tetap di `tenant_users`. Setiap login, baris di-sync dari pusat ke cabang.

---

## Logika koneksi DB

### Connection resolver

```php
// app/Services/TenantManager.php

class TenantManager {
    protected ?string $currentTenantId = null;

    public function setCurrent(string $tenantId): void {
        $tenant = DB::connection('central')
            ->table('tenants')->where('id', $tenantId)->first();
        if (!$tenant || $tenant->status !== 'aktif') {
            throw new TenantNotAvailableException();
        }
        config(['database.connections.tenant' => [
            'driver'   => 'mysql',
            'host'     => config('database.connections.central.host'),
            'database' => $tenant->db_name,
            // ... same auth as central
        ]]);
        DB::purge('tenant');
        $this->currentTenantId = $tenantId;
    }

    public function id(): ?string { return $this->currentTenantId; }
}
```

### Middleware

```php
// app/Http/Middleware/ResolveTenant.php — runs after auth
$user = auth()->user();
if ($user->role === 'super_admin') {
    // super-admin tidak punya tenant, biarkan tenant unresolved
    return $next($request);
}
if (!$user->tenant_id) abort(403, 'Akun tidak terikat ke cabang');
app(TenantManager::class)->setCurrent($user->tenant_id);
return $next($request);
```

Semua Eloquent model bisnis pakai `protected $connection = 'tenant'`. Model di DB pusat (Tenant, TenantUser, RoleTemplate, AuditLog) pakai `protected $connection = 'central'`.

---

## Onboarding cabang baru

Flow dijalankan oleh super-admin via UI `Pengaturan → Cabang → Tambah Cabang`. Job background `BootstrapTenantJob`:

```
1. Validasi: kode unik, db_name belum ada
2. CREATE DATABASE monitor_lpg_<kode>
3. Set tenants.bootstrap_state = 'seeding'
4. Jalankan migration ke DB baru — pakai migration files yang sama
5. Kalau ada parent_id:
   a. Copy referensi MASTER dari parent → child:
      - pangkalans (semua, tanpa transaksi)
      - spbes
      - karyawans (opsional — biasanya tidak ikut, beda kru per cabang)
      - armadas (opsional — beda kendaraan)
      - referensi_harga
      - role_templates customization
      - settings_agen TEMPLATE (alamat dikosongkan, agen identity diisi dari wizard)
   b. SKIP transaksi: transactions, surat_jalan_*, brimola_*, audit_*, scrape_logs
6. Insert tenant_users — admin pertama (dari wizard) sebagai 'direktur'
7. Sync ke cabang.users (mirror)
8. Set tenants.bootstrap_state = 'ready', status = 'aktif'
9. Audit log: 'tenant.bootstrap.success'
10. Email notifikasi ke direktur cabang baru (kredensial sementara)
```

Kalau gagal di langkah manapun: `bootstrap_state = 'failed'`, simpan error ke `bootstrap_log`, **jangan hapus DB** (untuk forensik). Super-admin bisa retry atau drop manual.

---

## Privilege model

Tiga lapis:

1. **Role default** — di `role_templates.privileges` (JSON). 5 role bawaan: `super_admin`, `direktur`, `manajer`, `admin`, `driver`.
2. **Tenant override** — boleh? **Tidak**. Privilege role berlaku global untuk konsistensi audit. Kalau cabang butuh role custom, buat role baru di template (mis. `manajer_keuangan`).
3. **User override** — kolom `tenant_users.privileges` (JSON). Boleh menambahkan permission individu di atas role-nya, tidak boleh mengurangi (untuk akuntabilitas — kalau mau cabut akses, ubah role).

Format privilege:

```json
{
  "menu.dashboard": true,
  "menu.nik.view": true,
  "menu.nik.export": false,
  "menu.brimola.view": true,
  "menu.brimola.sync": false,
  "menu.audit.view": true,
  "menu.audit.realokasi": false,
  "menu.pangkalan.crud": true,
  "menu.settings.tenant": false,
  "menu.settings.user": false,
  "data.scope": "tenant"
}
```

`data.scope = "tenant"` untuk user biasa, `"global"` untuk super-admin. Field ini di-cek di setiap query: `where('tenant_id', $user->tenant_id)` di middleware.

---

## Super-admin: konsol terpisah

Super-admin masuk ke **shell berbeda** dari user cabang — tidak ada Dashboard MAP, tidak ada Surat Jalan. Yang ada:

```
Holding Console
├── Cabang             — list, tambah, suspend, lihat status bootstrap
├── Pengguna           — semua user lintas cabang, filter per cabang/role
├── Role & Privilege   — role template editor
├── Audit Log          — semua action lintas cabang
└── Impersonate        — masuk ke konsol cabang sebagai user tertentu
                        (audit log mencatat 'impersonate.start' dan 'impersonate.end')
```

**Impersonate** = super-admin sementara "menjadi" user lain untuk debugging. Banner kuning permanen di seluruh halaman selama impersonate aktif. Tombol "Keluar Impersonate" selalu tampil.

---

## Aturan KETAT

1. **Super-admin tidak pernah melihat transaksi langsung** — harus via impersonate, agar audit trail tegas.
2. **Tenant `parent_id` boleh berubah?** **Tidak setelah aktif**. Cabang induk hanya berlaku saat bootstrap.
3. **Hapus cabang?** **Tidak.** Hanya bisa `status = tutup` — DB tetap ada untuk arsip.
4. **Re-bootstrap cabang yang sudah ready?** **Tidak.** Buat cabang baru.
5. **Pindahkan user antar cabang?** **Tidak.** Buat user baru di cabang tujuan; user lama di-nonaktifkan.
6. **Migrations** harus jalan ke `central` + `template` + semua cabang aktif. Pakai command custom `php artisan tenant:migrate --all`.
7. **Seeder master** untuk cabang baru ada di `database/seeders/TenantTemplateSeeder.php`.

---

## Setting environment

```env
DB_CONNECTION=central
DB_HOST=127.0.0.1
DB_DATABASE=central_admin   # was: monitor_lpg

TENANT_DB_PREFIX=monitor_lpg_
TENANT_DEFAULT_PARENT=KRW   # cabang Karawang sebagai default induk
```

---

## Apa yang berubah di app yang sudah ada

- Tabel `users` di `monitor_lpg` lama → di-migrate jadi `tenant_users` di `central_admin`, dengan `tenant_id = KRW` (cabang default Karawang).
- Tabel `pangkalan_sessions`, `pangkalan_tokens` tetap per-cabang.
- Sidebar baru: menambahkan eyebrow "**Cabang Karawang**" di brand area sidebar. Super-admin sidebarnya beda total — lihat `ui_kits/admin/SuperAdmin.jsx`.
- Login screen: ditambah deteksi super-admin (`role === 'super_admin'`) → route ke `/holding` bukan `/dashboard`.

---

## Migration order (untuk Claude Code)

1. Buat DB `central_admin`, jalankan migration tabel `tenants`, `tenant_users`, `role_templates`, `audit_log`.
2. Buat DB `monitor_lpg_template` (kosong, hanya schema).
3. Insert tenants record untuk Karawang: `kode='KRW'`, `db_name='monitor_lpg'` (gunakan DB existing sebagai cabang pertama).
4. Migrate users existing → `tenant_users` dengan `tenant_id = KRW`.
5. Update `config/database.php` — tambah connection `central` & `template`.
6. Implement `TenantManager` service.
7. Tambah middleware `ResolveTenant` di `web` & `api` group.
8. Test login direktur Karawang — pastikan masih bisa lihat data lama.
9. Test create super-admin manual via tinker.
10. Build UI super-admin (sudah ada mock di `ui_kits/admin/SuperAdmin.jsx`).

---

*Disesuaikan dari mock di `ui_kits/admin/`. Untuk pertanyaan: kembali ke design system project.*
