<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NikViolation extends Model
{
    protected $fillable = [
        'nationality_id', 'name',
        'prev_transaction_date', 'curr_transaction_date',
        'gap_days', 'min_interval_days', 'severity', 'is_resolved',
    ];

    protected $casts = [
        'prev_transaction_date' => 'date',
        'curr_transaction_date' => 'date',
        'is_resolved'           => 'boolean',
    ];

    public function scopeUnresolved($q)
    {
        return $q->where('is_resolved', false);
    }
}
