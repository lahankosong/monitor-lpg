<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Jadwal otomatis: batch scrape setiap Minggu malam jam 21:00
Schedule::command('batch:scrape', [
    '--from' => now()->startOfWeek()->toDateString(),
    '--to'   => now()->toDateString(),
])->weeklyOn(0, '21:00')->onSuccess(function () {
    \Illuminate\Support\Facades\Log::info('[Scheduler] Batch scrape mingguan selesai');
})->onFailure(function () {
    \Illuminate\Support\Facades\Log::error('[Scheduler] Batch scrape mingguan gagal');
});
