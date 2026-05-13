<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PangkalanStock extends Model
{
    protected $fillable = [
        'pangkalan_id',
        'store_name',
        'registration_id',
        'stock_available',
        'stock_redeem',
        'sold',
        'stock_date',
        'last_stock',
        'last_stock_date',
        'recorded_at',
    ];

    protected $casts = [
        'recorded_at'     => 'date',
        'stock_available' => 'integer',
        'stock_redeem'    => 'integer',
        'sold'            => 'integer',
        'last_stock'      => 'integer',
    ];
}