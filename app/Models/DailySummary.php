<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailySummary extends Model
{
    protected $fillable = [
        'pangkalan_id', 'summary_date',
        'sold', 'modal', 'profit', 'gross',
    ];

    protected $casts = [
        'summary_date' => 'date',
        'sold'         => 'integer',
        'modal'        => 'integer',
        'profit'       => 'integer',
        'gross'        => 'integer',
    ];
}
