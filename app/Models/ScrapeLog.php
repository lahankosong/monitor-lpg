<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScrapeLog extends Model
{
    protected $fillable = [
        'pangkalan_id', 'start_date', 'end_date',
        'status', 'records_fetched', 'records_saved',
        'error_message', 'scraped_at',
    ];

    protected $casts = [
        'scraped_at'      => 'datetime',
        'start_date'      => 'date',
        'end_date'        => 'date',
        'records_fetched' => 'integer',
        'records_saved'   => 'integer',
    ];
}
