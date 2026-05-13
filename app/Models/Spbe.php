<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Spbe extends Model
{
    protected $fillable = [
        'kode_spbe', 'nama_spbe', 'ship_to', 'kode_plant',
        'alamat', 'telepon', 'no_rekening', 'nama_bank', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function kitirs()
    {
        return $this->hasMany(Kitir::class);
    }

    public function scopeAktif($q) { return $q->where('is_active', true); }
}
