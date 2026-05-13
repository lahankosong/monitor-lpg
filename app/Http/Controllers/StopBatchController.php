<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class StopBatchController extends Controller
{
    /**
     * Set flag stop — BatchScrapeCommand cek flag ini setiap selesai satu pangkalan
     */
    public function stop()
    {
        Cache::put('batch_scrape_stop', true, now()->addHours(1));
        Cache::forget('batch_scrape_running');
        Log::info('[BatchScrape] Dihentikan oleh user via dashboard');

        return response()->json(['success' => true, 'message' => 'Batch scraping dihentikan']);
    }

    public function statusApi()
    {
        return response()->json([
            'running'     => Cache::get('batch_scrape_running', false),
            'stop_flag'   => Cache::get('batch_scrape_stop', false),
            'progress'    => Cache::get('batch_scrape_progress', []),
            'last_result' => Cache::get('batch_scrape_last_result'),
        ]);
    }
}
