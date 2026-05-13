<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PangkalanToken extends Model
{
    protected $fillable = [
        'pangkalan_id', 'label', 'token',
        'token_issued_at', 'token_expires_at', 'is_active',
    ];

    protected $casts = [
        'token_issued_at'  => 'datetime',
        'token_expires_at' => 'datetime',
        'is_active'        => 'boolean',
    ];
}
