# CLAUDE.md — Monitor LPG Subsidi
# Versi: 2.0 · Diperbarui: Mei 2026
# Baca SEBELUM mengubah apapun di repository ini.

---

## Gambaran Proyek

Aplikasi Laravel 12 untuk operasional **Agen LPG Subsidi** — mulai dari monitoring
transaksi NIK di MyPertamina, manajemen distribusi, akuntansi, hingga (roadmap)
multi-cabang. Dibangun untuk PT. Muara Gas Elpiji, Banjarnegara.

**Stack:** Laravel 12.58 / PHP 8.2 / MySQL / XAMPP Windows (localhost:8000)  
**Path lokal:** `C:\xampp\htdocs\monitor-lpg`  
**Database:** `monitor_lpg`  
**Python scraping:** Playwright + aiohttp (ada di `scripts/`)

---

## Perintah Sehari-hari

```bash
php artisan serve              # Dev server port 8000
php artisan migrate            # Jalankan migration baru
php artisan migrate:rollback   # Rollback 1 batch
php artisan route:clear        # Wajib setelah ubah web.php
php artisan view:clear         # Wajib setelah ubah blade
php artisan config:clear       # Setelah ubah .env
php artisan queue:work         # Background job (scraping)
php artisan batch:scrape       # Scraping manual semua pangkalan
php artisan scrape:clear       # Kosongkan data scraping (tanpa hapus master)
php artisan akun:clean-pending # Hapus duplikat pending_ di pangkalan_sessions
composer dev                   # Server + queue + log sekaligus
```

---

## Dua Modul Utama

### 1. Monitor NIK (modul asal)
Scraping transaksi per NIK dari MyPertamina, deteksi pelanggaran interval pembelian.

**Aturan pelanggaran:**
- Rumah Tangga: maks 1 pembelian per 7 hari
- Jam abnormal: transaksi di luar 05:30–20:00 WIB
- Status: `aman` / `warn` / `alert` / `new`

**Alur scraping:**
```
pangkalan_sessions (DB) → GitHub Actions fetch via GET /api/github-actions/accounts
→ Python Playwright login MyPertamina → capture Bearer token
→ POST /api/github-actions/tokens → batch:scrape artisan command
→ transactions + nik_violations tersimpan
```

**File penting:**
- `app/Http/Controllers/AkunPangkalanController.php` — CRUD akun + trigger scraping
- `app/Http/Controllers/BatchScrapeController.php` — batch scrape + importResult()
- `app/Console/Commands/BatchScrapeCommand.php` — proses output Python, tulis ke `batch_realtime_log.jsonl`
- `app/Http/Controllers/GithubActionsController.php` — API tanpa auth session (X-API-Key)
- `scripts/auto_login_batch.py` — Playwright login + fetch transaksi
- `scripts/scrape_for_actions.py` — fetch credentials dari DB Laravel via API, kirim token

**Catatan penting scraping:**
- Credentials diambil dari `pangkalan_sessions` (BUKAN `scripts/accounts.json` — sudah deprecated)
- Setiap akun deduplikasi per `username` — prioritaskan UUID asli, bukan `pending_`
- Log realtime ke `storage/app/batch_realtime_log.jsonl` (polling tiap 2 detik dari frontend)
- GitHub Actions auth pakai header `X-API-Key` = `env('GITHUB_ACTIONS_KEY')`

### 2. Agen (modul distribusi & akuntansi)
Sistem lengkap operasional agen LPG subsidi.

**Alur bisnis utama (JANGAN UBAH URUTAN INI):**
```
Kitir (jadwal dari Pertamina)
  → Tebusan (bayar ke SPBE)
    → Surat Jalan (panduan distribusi)
      → Distribusi / Realisasi (driver input qty terkirim)
        → BRImola (pembayaran pangkalan via BRIVA)
          → Audit Alokasi FIFO (rekonsiliasi bayar vs distribusi)
            → Buku Besar (jurnal akuntansi otomatis)
```

---

## Aturan Bisnis Kritis — JANGAN UBAH

1. **Gendongan hanya untuk armada yang sama** — tidak bisa pindah tanpa lewat gudang
2. **qty_terima ≤ qty_jadwal + qty_tambahan** — diblokir real-time JS + server-side
3. **Sisa distribusi wajib dilaporkan nasibnya** — SUM(sisa) harus = qty_jadwal - qty_terima
4. **Saldo BRImola kumulatif** — TIDAK di-reset per bulan
5. **Alokasi pembayaran ke distribusi = FIFO** — BRIVA paling lama dipakai duluan
6. **Pangkalan kerjasama** kena uang kerjasama, pangkalan mandiri tidak
7. **Jurnal akuntansi double-entry** — debit wajib = kredit, ditolak jika tidak balance
8. **Credentials scraping dari DB** — bukan dari accounts.json
9. **Buku besar otomatis** — setiap tebusan, distribusi selesai, BRImola verified, dan kas kecil
   keluar → jurnal terbentuk via `JurnalService`. Kalau jurnal gagal, transaksi tetap tersimpan
   (try-catch, log warning saja)

---

## Struktur Database Lengkap

### Monitor NIK
| Tabel | Isi |
|-------|-----|
| `transactions` | Transaksi per NIK dari MyPertamina |
| `nik_violations` | Pelanggaran interval yang terdeteksi |
| `pangkalan_sessions` | Akun login MyPertamina (email + PIN terenkripsi Crypt) |
| `pangkalan_tokens` | Bearer token hasil scraping |
| `scrape_logs` | Log per scraping per pangkalan |
| `daily_summaries` | Ringkasan harian per pangkalan |

### Operasional
| Tabel | Isi |
|-------|-----|
| `kitirs` | Header kitir (jadwal dari Pertamina) |
| `kitir_details` | Detail per SPBE per kitir |
| `tebusan_kitirs` | Header tebusan (bayar ke SPBE) |
| `tebusan_kitir_details` | Detail tebusan per SA |
| `surat_jalan_headers` | Header SJ (per trip per armada) |
| `surat_jalan_details` | Detail per pangkalan per SJ |
| `sj_sisa_distribusi` | Nasib sisa distribusi (4 tipe) |
| `sj_pengalihan` | Pengalihan ke pangkalan lain |
| `sj_detail_tambahan` | Tambahan qty dari sumber lain |

### Stok & Gudang
| Tabel | Isi |
|-------|-----|
| `stok_armada` | Saldo gendongan per armada per trip |
| `gudang_stok` | Ledger masuk/keluar stok gudang (FIFO) |
| `transaksi_antar_agen` | Titip/pinjam tabung ke agen lain |

### BRImola & Audit
| Tabel | Isi |
|-------|-----|
| `brimola_transaksi` | Pembayaran masuk via BRIVA |
| `brimola_import_batch` | Tracking per batch import Excel |
| `audit_distribusi_bayar` | Alokasi FIFO pembayaran → distribusi |
| `saldo_pangkalan` | Cache saldo kredit/piutang per pangkalan |

### Akuntansi
| Tabel | Isi |
|-------|-----|
| `akun_keuangan` | Master akun (1001-5007, 17 akun default) |
| `jurnal_headers` | Header jurnal (no_jurnal, modul, referensi) |
| `jurnal_details` | Detail debit/kredit per baris jurnal |
| `saldo_awal_akun` | Saldo awal per akun untuk cut-off |
| `piutang_kerjasama` | Tagihan uang sewa tabung per distribusi |
| `piutang_kerjasama_bayar` | Pembayaran piutang kerjasama (partial) |
| `kas_kecil` | Transaksi kas kecil operasional |
| `kas_kecil_saldo` | Cache saldo kas kecil |

### Master Data
| Tabel | Isi |
|-------|-----|
| `pangkalans` | Data pangkalan (tipe: kerjasama/mandiri) |
| `spbes` | Data SPBE (depot Pertamina) |
| `karyawans` | Data karyawan + driver |
| `armadas` | Data armada (kendaraan) |
| `harga_referensis` | Referensi harga (tebus_refil, jual_pangkalan, sewa_tabung, dll) |
| `users` | User login (role: direktur/manajer/admin/driver) |

---

## Akun Keuangan (Chart of Accounts)

```
ASET (1xxx)          KEWAJIBAN (2xxx)     MODAL (3xxx)
1001 Kas Kecil       2001 Utang Pertamina  3001 Modal Disetor
1002 Rek. Giro BRI   2002 Titipan Pangk.  3002 Prive/Penarikan
1003 Piutang Dagang  2003 Utang ke Pemilik 3003 Laba Ditahan
1004 Piutang Kerjasama
1005 Persediaan Tabung

PENDAPATAN (4xxx)    BEBAN (5xxx)
4001 Penjualan Refil 5001 HPP Tebusan
4002 Uang Kerjasama  5002 Beban BBM
                     5003 Beban Gaji
                     5004 Beban Servis
                     5005 Beban STNK
                     5006 Beban Kantor
                     5007 Beban Lain-lain
```

Jurnal otomatis terbentuk dari:
- `TebusanController::store()` → modul `tebusan` (D:2001 K:1002)
- `DistribusiController::tutupTrip()` → modul `distribusi` (D:1003 K:4001 + D:5001 K:1005)
- `BrimolaController::verify()` → modul `brimola` (D:1002 K:1003)
- `AkuntansiController::bayarPiutang()` → modul `kerjasama` (D:1001/1002 K:4002)
- `AkuntansiController::kasStore()` → modul `kas_kecil` (D:500x K:1001)
- `BukuBesarController::modalStore()` → modal/prive/utang_pemilik

---

## Role User & Akses

| Role | Akses |
|------|-------|
| `direktur` | Semua menu termasuk laporan keuangan |
| `manajer` | Semua operasional + keuangan, tidak bisa setting sistem |
| `admin` | Operasional + distribusi + database pangkalan |
| `driver` | Mode Driver saja |

**Default login development:**
- `direktur@lpgmonitor.local` / `direktur123`
- `manajer@lpgmonitor.local` / `manajer123`
- `admin@lpgmonitor.local` / `admin123`

Middleware `CheckRole` → `app/Http/Middleware/CheckRole.php`  
Helper role di `User` model: `isDirektur()`, `isManajer()`, `isAdmin()`, `isDriver()`

---

## Struktur Views

```
resources/views/
├── auth/
│   └── login.blade.php              ← dark theme, Poppins, accent hijau
├── layouts/
│   ├── app.blade.php                ← layout utama desktop (JANGAN UBAH sembarangan)
│   ├── driver.blade.php             ← layout mobile driver (dark, bottom-nav)
│   └── partials/
│       └── sidebar.blade.php        ← UPDATE INI saja saat ada perubahan menu
├── dashboard/                       ← Monitor NIK
│   ├── akun-pangkalan.blade.php     ← kelola akun scraping (popup progress)
│   ├── batch-scrape.blade.php       ← trigger batch manual
│   ├── nik.blade.php / nik-detail
│   └── batch-status.blade.php
├── agen/
│   ├── operasional/kitir/           ← manajemen kitir
│   ├── operasional/surat-jalan/     ← SJ header & detail
│   ├── distribusi/                  ← realisasi, stok & gudang, laporan
│   ├── driver/                      ← mode driver mobile
│   ├── akuntansi/
│   │   ├── brimola/                 ← index, audit, audit-detail
│   │   ├── piutang-kerjasama.blade.php
│   │   ├── kas-kecil.blade.php
│   │   ├── dashboard.blade.php
│   │   ├── jurnal.blade.php         ← jurnal umum + modal/prive shortcut
│   │   ├── buku-besar.blade.php     ← semua akun per periode
│   │   ├── buku-besar-print.blade.php ← versi cetak/PDF
│   │   ├── laba-rugi.blade.php
│   │   └── neraca.blade.php
│   └── database/                    ← pangkalan, SPBE, karyawan, armada
└── Map/
    └── dashboard.blade.php          ← dashboard MAP utama
```

---

## Konvensi CSS & UI

**CSS:** Inline style dengan CSS variables — BUKAN class Tailwind di views agen.

**Variabel yang tersedia:**
```css
var(--bg)       /* canvas halaman */
var(--surface)  /* background card */
var(--border)   /* border tipis */
var(--text)     /* teks utama */
var(--muted)    /* teks sekunder */
var(--accent)   /* warna utama CTA */
```

**Tema yang tersedia:** Normal (biru langit) · Dark (biru tua) · Classic (hijau tua) · Modern (ungu)

**Aturan desain (JANGAN DILANGGAR):**
- Border radius: `8px` card biasa, maks `16px` modal
- Sidebar SELALU dark (obsidian) di semua tema
- Hover button: `opacity:.9` — bukan transform/shadow
- Status pakai badge pill berwarna, bukan emoji murni
- Teks Bahasa Indonesia, sentence case
- Angka besar di stat cards pakai font lebih besar dan bold
- Eyebrow section label: `10px / font-weight:600 / letter-spacing:.08em / UPPERCASE`
- Inline style separator: ` · ` (middle dot)

---

## API Endpoints (tanpa auth session)

```
GET  /api/github-actions/accounts  → credentials ke Python scraper
POST /api/github-actions/tokens    → terima token dari Python scraper
```
**Auth:** Header `X-API-Key` = `env('GITHUB_ACTIONS_KEY')`  
**Middleware CSRF:** `withoutMiddleware(VerifyCsrfToken)` karena POST dari luar browser

---

## Services

| Service | Lokasi | Fungsi |
|---------|--------|--------|
| `JurnalService` | `app/Services/JurnalService.php` | Buat jurnal double-entry, validasi balance, hitung saldo akun |
| `AuditAlokasiService` | `app/Services/AuditAlokasiService.php` | Alokasi FIFO BRImola → distribusi, hitung saldo pangkalan |

---

## Yang BELUM Dibangun (Roadmap)

### Segera Dibutuhkan
- **Manajemen Gudang** — tabel `gudang_stok` sudah ada tapi belum ada UI dedicated:
  - Halaman stok gudang real-time (masuk dari DO, keluar ke distribusi, stok antar agen)
  - Riwayat mutasi gudang per bulan
  - Alert stok minimum
  - Opname fisik gudang (penyesuaian selisih)

- **Notifikasi dalam aplikasi** — tabel belum ada:
  - Tabel `notifikasi` (user_id, tipe, pesan, is_read, created_at)
  - Badge di topbar per role (direktur/manajer dapat notif tebusan, SJ selesai, piutang jatuh tempo)
  - Halaman inbox notifikasi

- **Halaman Settings** — route placeholder belum dibangun:
  - Profil agen (nama, alamat, logo)
  - Ganti password
  - Preferensi notifikasi

### Jangka Menengah
- **Laporan cetak distribusi** — Berita Acara Distribusi per trip (PDF)
- **Laporan Laba Rugi & Neraca PDF** dengan kop surat (sudah ada view print, perlu integrasi dompdf)
- **Manajemen Karyawan lengkap** — sekarang hanya CRUD, belum ada gaji/absensi
- **Dashboard Direktur** — KPI ringkas: penjualan bulan ini, piutang outstanding, saldo kas, tren NIK violation

### Jangka Panjang (Lihat CLAUDE-multitenant.md)
- **Multi-tenant / Multi-cabang** — database-per-tenant, `central_admin` DB, `TenantManager` service
- **Holding Console** untuk super-admin (6 halaman: ringkasan, cabang, user, role, audit, pengaturan)
- **CabangWizard** — wizard 4 langkah tambah cabang baru
- **Impersonate** — super-admin masuk ke cabang dengan banner kuning
- **Privilege Matrix** — `lpg_can()` helper per menu per role
- **Audit Log lintas cabang**

---

## File Konfigurasi Penting

```
.env                          → DB, app key, GITHUB_ACTIONS_KEY, PYTHON_PATH
config/database.php           → koneksi MySQL
routes/web.php                → semua route (v15, 100+ routes)
app/Http/Middleware/CheckRole.php → middleware role
bootstrap/app.php             → daftarkan alias middleware 'role'
scripts/accounts.json         → DEPRECATED, jangan dipakai lagi
storage/app/batch_realtime_log.jsonl → log scraping realtime
```

---

## Hal yang Harus Dilakukan Setelah Edit File Ini

Jika mengubah **routes:** `php artisan route:clear`  
Jika mengubah **blade:** `php artisan view:clear`  
Jika mengubah **config/env:** `php artisan config:clear`  
Jika menambah **migration:** `php artisan migrate`  
Jika mengubah **sidebar:** update hanya `partials/sidebar.blade.php`, bukan `app.blade.php`

---

## Hal yang TIDAK Boleh Diubah Tanpa Diskusi

- Logika FIFO di `AuditAlokasiService::alokasiPangkalan()`
- Method `tutupTrip()` di `DistribusiController`
- Kalkulasi `qty_maks` di `SuratJalanDetail`
- Struktur tabel `sj_sisa_distribusi` (4 tipe: alih_pangkalan, stok_armada, gudang_sendiri, titip_agen_lain)
- Sistem double-entry di `JurnalService` (debit wajib = kredit)
- Kode akun `akun_keuangan` (1001-5007) — perubahan bisa merusak jurnal historis
- `pangkalan_sessions` deduplikasi logic — hanya ambil UUID asli, bukan `pending_`
