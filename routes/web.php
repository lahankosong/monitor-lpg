<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Map\MapDashboardController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\Agen\DatabaseController;
use App\Http\Controllers\Agen\KitirController;
use App\Http\Controllers\Agen\TebusanController;
use App\Http\Controllers\Agen\HargaReferensiController;
use App\Http\Controllers\Agen\SuratJalanController;
use App\Http\Controllers\Agen\PangkalanExportImportController;
use App\Http\Controllers\Agen\DistribusiController;
use App\Http\Controllers\Agen\BrimolaController;
use App\Http\Controllers\Agen\AuditBrimolaController;
use App\Http\Controllers\Agen\DriverController;
use App\Http\Controllers\TokenCaptureController;
use App\Http\Controllers\TokenInputController;
use App\Http\Controllers\BatchScrapeController;
use App\Http\Controllers\AkunPangkalanController;
use App\Http\Controllers\StopBatchController;
use App\Http\Controllers\LoginController;
use Illuminate\Support\Facades\Route;

// ── Auth ─────────────────────────────────────────────────────────
Route::get('/login',  [LoginController::class, 'showLogin'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->name('login.post');
Route::post('/logout',[LoginController::class, 'logout'])->name('logout')->middleware('auth');

Route::prefix('dashboard')->name('dashboard.')->middleware(['auth'])->group(function () {

    // ── MAP Dashboard ─────────────────────────────────────────────
    Route::get('/', [MapDashboardController::class, 'index'])->name('index');
    Route::get('/export', [ExportController::class, 'export'])->name('export');

    // ── Monitor NIK ───────────────────────────────────────────────
    Route::get('/nik',        [DashboardController::class, 'nikList'])->name('nik.list');
    Route::get('/nik/detail', [DashboardController::class, 'nikDetail'])->name('nik.detail');
    Route::get('/export/csv', [DashboardController::class, 'exportCsv'])->name('export.csv');
    Route::post('/scrape',    [DashboardController::class, 'triggerScrape'])->name('scrape');
    Route::post('/token',     [DashboardController::class, 'saveToken'])->name('token.save');

    // ── Token ─────────────────────────────────────────────────────
    Route::get('/token/input',     [TokenInputController::class, 'index'])->name('token.input');
    Route::post('/token/input',    [TokenInputController::class, 'store'])->name('token.input.store');
    Route::post('/token/rescrape', [TokenInputController::class, 'rescrape'])->name('token.rescrape');

    // ── Akun Pangkalan (MAP Scraping) ─────────────────────────────
    Route::get('/akun',                    [AkunPangkalanController::class, 'index'])->name('akun.index');
    Route::post('/akun',                   [AkunPangkalanController::class, 'store'])->name('akun.store');
    Route::get('/akun/status',             [AkunPangkalanController::class, 'statusApi'])->name('akun.status');
    Route::post('/akun/import-json',       [AkunPangkalanController::class, 'importFromJson'])->name('akun.import-json');
    Route::post('/akun/scrape-all',        [AkunPangkalanController::class, 'scrapeAll'])->name('akun.scrape-all');
    Route::get('/akun/{id}/edit',          [AkunPangkalanController::class, 'edit'])->name('akun.edit');
    Route::get('/akun/{id}/password',      [AkunPangkalanController::class, 'showPassword'])->name('akun.show-password');
    Route::put('/akun/{id}',               [AkunPangkalanController::class, 'update'])->name('akun.update');
    Route::delete('/akun/{id}',            [AkunPangkalanController::class, 'destroy'])->name('akun.destroy');
    Route::patch('/akun/{id}/toggle',      [AkunPangkalanController::class, 'toggleActive'])->name('akun.toggle');
    Route::post('/akun/{id}/scrape',       [AkunPangkalanController::class, 'scrapeOne'])->name('akun.scrape-one');

    // ── Agen — Database Master ────────────────────────────────────
    Route::prefix('agen/database')->name('agen.db.')->group(function () {
        // Profil Agen
        Route::get('/profil',  [DatabaseController::class, 'agen'])->name('agen');
        Route::put('/profil',  [DatabaseController::class, 'agenUpdate'])->name('agen.update');

        // SPBE
        Route::get('/spbe',                    [DatabaseController::class, 'spbe'])->name('spbe');
        Route::post('/spbe',                   [DatabaseController::class, 'spbeStore'])->name('spbe.store');
        Route::put('/spbe/{spbe}',             [DatabaseController::class, 'spbeUpdate'])->name('spbe.update');
        Route::delete('/spbe/{spbe}',          [DatabaseController::class, 'spbeDestroy'])->name('spbe.destroy');
        Route::patch('/spbe/{spbe}/toggle',    [DatabaseController::class, 'spbeToggle'])->name('spbe.toggle');

        // Pangkalan
        Route::get('/pangkalan',                     [DatabaseController::class, 'pangkalan'])->name('pangkalan');
        Route::post('/pangkalan',                    [DatabaseController::class, 'pangkalanStore'])->name('pangkalan.store');
        Route::post('/pangkalan/import',             [DatabaseController::class, 'pangkalanImport'])->name('pangkalan.import');
        Route::get('/pangkalan/export',              [PangkalanExportImportController::class, 'export'])->name('pangkalan.export');
        Route::post('/pangkalan/import-xlsx',        [PangkalanExportImportController::class, 'import'])->name('pangkalan.import-xlsx');
        Route::get('/pangkalan/template',            [PangkalanExportImportController::class, 'template'])->name('pangkalan.template');
        Route::put('/pangkalan/{pangkalan}',         [DatabaseController::class, 'pangkalanUpdate'])->name('pangkalan.update');
        Route::delete('/pangkalan/{pangkalan}',      [DatabaseController::class, 'pangkalanDestroy'])->name('pangkalan.destroy');
        Route::patch('/pangkalan/{pangkalan}/toggle',[DatabaseController::class, 'pangkalanToggle'])->name('pangkalan.toggle');
        Route::get('/pangkalan/{pangkalan}/perjanjian', [DatabaseController::class, 'pangkalanPerjanjian'])->name('pangkalan.perjanjian');

        // Karyawan
        Route::get('/karyawan',                    [DatabaseController::class, 'karyawan'])->name('karyawan');
        Route::post('/karyawan',                   [DatabaseController::class, 'karyawanStore'])->name('karyawan.store');
        Route::put('/karyawan/{karyawan}',         [DatabaseController::class, 'karyawanUpdate'])->name('karyawan.update');
        Route::delete('/karyawan/{karyawan}',      [DatabaseController::class, 'karyawanDestroy'])->name('karyawan.destroy');
        Route::patch('/karyawan/{karyawan}/toggle',[DatabaseController::class, 'karyawanToggle'])->name('karyawan.toggle');

        // Armada
        Route::get('/armada',                    [DatabaseController::class, 'armada'])->name('armada');
        Route::post('/armada',                   [DatabaseController::class, 'armadaStore'])->name('armada.store');
        Route::put('/armada/{armada}',           [DatabaseController::class, 'armadaUpdate'])->name('armada.update');
        Route::delete('/armada/{armada}',        [DatabaseController::class, 'armadaDestroy'])->name('armada.destroy');
        Route::patch('/armada/{armada}/toggle',  [DatabaseController::class, 'armadaToggle'])->name('armada.toggle');
    }); // ── end agen/database ──

    // ── Agen — Operasional ────────────────────────────────────────
    Route::prefix('agen/operasional')->name('agen.operasional.')->group(function () {
        // Kitir
        Route::get('/kitir',                              [KitirController::class, 'index'])->name('kitir.index');
        Route::post('/kitir',                             [KitirController::class, 'store'])->name('kitir.store');
        Route::get('/kitir/{kitir}',                      [KitirController::class, 'show'])->name('kitir.show');
        Route::delete('/kitir/{kitir}',                   [KitirController::class, 'destroy'])->name('kitir.destroy');
        Route::patch('/kitir/detail/{detail}/status',     [KitirController::class, 'updateDetailStatus'])->name('kitir.detail.status');

        // Surat Jalan
        Route::get('/surat-jalan',                         [SuratJalanController::class, 'index'])->name('sj.index');
        Route::post('/surat-jalan',                        [SuratJalanController::class, 'store'])->name('sj.store');
        Route::get('/surat-jalan/{suratJalan}',            [SuratJalanController::class, 'show'])->name('sj.show');
        Route::get('/surat-jalan/{suratJalan}/cetak-spbe', [SuratJalanController::class, 'cetakSpbe'])->name('sj.cetak-spbe');
        Route::get('/surat-jalan/{suratJalan}/cetak-dist', [SuratJalanController::class, 'cetakDistribusi'])->name('sj.cetak-distribusi');
        Route::patch('/surat-jalan/{suratJalan}/lo',       [SuratJalanController::class, 'updateLo'])->name('sj.update-lo');
        Route::patch('/surat-jalan/{suratJalan}/batal',    [SuratJalanController::class, 'batal'])->name('sj.batal');
        Route::delete('/surat-jalan/{suratJalan}',          [SuratJalanController::class, 'destroy'])->name('sj.destroy');
        Route::patch('/surat-jalan/detail/{detail}/realisasi', [SuratJalanController::class, 'updateRealisasi'])->name('sj.realisasi');
    }); // ── end agen/operasional ──

    // ── Agen — Akuntansi PSO ──────────────────────────────────────
    Route::prefix('agen/akuntansi')->name('agen.akuntansi.')->group(function () {
        // Referensi Harga
        Route::get('/harga',            [HargaReferensiController::class, 'index'])->name('harga.index');
        Route::post('/harga',           [HargaReferensiController::class, 'store'])->name('harga.store');
        Route::delete('/harga/{harga}', [HargaReferensiController::class, 'destroy'])->name('harga.destroy');
        Route::get('/harga/api',        [HargaReferensiController::class, 'api'])->name('harga.api');

        // Tebusan Kitir
        Route::get('/tebusan',               [TebusanController::class, 'index'])->name('tebusan.index');
        Route::post('/tebusan',              [TebusanController::class, 'store'])->name('tebusan.store');
        Route::get('/tebusan/kitir-detail',  [TebusanController::class, 'getKitirDetail'])->name('tebusan.kitir-detail');

        // BRImola
        Route::prefix('brimola')->name('brimola.')->group(function () {
            Route::get('/',        [BrimolaController::class, 'index'])->name('index');
            Route::post('/',       [BrimolaController::class, 'store'])->name('store');
            Route::post('/import', [BrimolaController::class, 'import'])->name('import');
            Route::post('/match',  [BrimolaController::class, 'match'])->name('match');
            Route::post('/verify', [BrimolaController::class, 'verify'])->name('verify');
            Route::get('/export',  [BrimolaController::class, 'export'])->name('export');

            // Audit alokasi FIFO
            Route::prefix('audit')->name('audit.')->group(function () {
                Route::get('/',                                [AuditBrimolaController::class, 'index'])->name('index');
                Route::get('/pangkalan/{id}',                  [AuditBrimolaController::class, 'detail'])->name('detail');
                Route::post('/realokasi-semua',                [AuditBrimolaController::class, 'realokasiSemua'])->name('realokasi-semua');
                Route::post('/realokasi-pangkalan/{id}',       [AuditBrimolaController::class, 'realokasiPangkalan'])->name('realokasi-pangkalan');
            });
        });
    }); // ── end agen/akuntansi ──

    // ── Agen — Distribusi ────────────────────────────────────────
    Route::prefix('agen/distribusi')->name('agen.distribusi.')->group(function () {
        Route::get('/',                        [DistribusiController::class, 'index'])->name('index');
        Route::put('/detail/{detail}',         [DistribusiController::class, 'update'])->name('update');
        Route::get('/laporan',                 [DistribusiController::class, 'laporan'])->name('laporan');
        Route::get('/stok',                    [DistribusiController::class, 'stok'])->name('stok');
        Route::post('/ambil-gudang',           [DistribusiController::class, 'ambilGudang'])->name('ambil-gudang');
        Route::post('/konfirmasi-gendongan',   [DistribusiController::class, 'konfirmasiGendongan'])->name('konfirmasi-gendongan');
        Route::patch('/antar-agen/{transaksi}/selesai', [DistribusiController::class, 'selesaiAntarAgen'])->name('selesai-antar-agen');
    }); // ── end agen/distribusi ──

    // ── Driver — Mobile Interface ─────────────────────────────────
    Route::prefix('agen/driver')->name('agen.driver.')->group(function () {
        Route::get('/',                        [DriverController::class, 'index'])->name('index');
        Route::put('/detail/{detail}',         [DriverController::class, 'inputRealisasi'])->name('input');
        Route::get('/histori',                 [DriverController::class, 'histori'])->name('histori');
        Route::get('/stok',                    [DriverController::class, 'stokView'])->name('stok');
    }); // ── end agen/driver ──

    // ── Batch Scrape ──────────────────────────────────────────────
    Route::get('/batch',           [BatchScrapeController::class, 'index'])->name('batch.index');
    Route::post('/batch/run',      [BatchScrapeController::class, 'run'])->name('batch.run');
    Route::post('/batch/accounts', [BatchScrapeController::class, 'updateAccounts'])->name('batch.accounts');
    Route::get('/batch/status',    [BatchScrapeController::class, 'status'])->name('batch.status');
    Route::post('/batch/stop',     [StopBatchController::class, 'stop'])->name('batch.stop');
    Route::get('/batch/status-api',[StopBatchController::class, 'statusApi'])->name('batch.status-api');

}); // ── end dashboard ──

Route::match(['OPTIONS','POST'], '/dashboard/token/capture', [TokenCaptureController::class, 'capture'])
    ->name('token.capture')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

Route::get('/', fn() => redirect()->route('dashboard.index'));
