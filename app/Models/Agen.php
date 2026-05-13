<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Agen extends Model
{
    protected $fillable = [
        'nama_agen', 'kode_agen', 'sold_to',
        'alamat', 'telepon', 'email', 'logo',
    ];

    /** Selalu ambil satu record (profil agen tunggal per cabang) */
    public static function profil(): ?self
    {
        return static::first();
    }
}
