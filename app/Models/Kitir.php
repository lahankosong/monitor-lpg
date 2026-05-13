<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Kitir extends Model
{
    protected $fillable = [
        'nomor_sa','sold_to','ship_to','spbe_id','jenis',
        'valid_from','valid_to','total_kuota','status','keterangan',
    ];

    protected $casts = [
        'valid_from' => 'date',
        'valid_to'   => 'date',
    ];

    public function spbe()    { return $this->belongsTo(Spbe::class); }
    public function details() { return $this->hasMany(KitirDetail::class); }
    public function tebusan() { return $this->hasMany(TebusanKitir::class); }

    public function getTotalTerbayarAttribute(): int
    {
        return $this->tebusan()->sum('jumlah_tabung_ditebus');
    }

    public function getSisaBelumTebusAttribute(): int
    {
        return $this->total_kuota - $this->total_terbayar;
    }

    public function scopeAktif($q)    { return $q->where('status', 'aktif'); }
    public function scopeByBulan($q, $bulan, $tahun) {
        return $q->whereMonth('valid_from', $bulan)->whereYear('valid_from', $tahun);
    }
}
