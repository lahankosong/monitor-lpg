<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class KitirDetail extends Model
{
    protected $fillable = [
        'kitir_id','tanggal','kuota_tabung','harga_tebus','status',
    ];
    protected $casts = ['tanggal' => 'date'];

    public function kitir()     { return $this->belongsTo(Kitir::class); }
    public function tebusan()   { return $this->hasMany(TebusanKitirDetail::class); }
    public function suratJalan(){ return $this->hasMany(SuratJalanHeader::class, 'kitir_detail_id'); }

    public function getTotalTebusAttribute(): int
    {
        return $this->tebusan()->sum('jumlah_tabung');
    }
    public function getSisaAttribute(): int
    {
        return $this->kuota_tabung - $this->total_tebus;
    }
    public function scopeBelumTebus($q) { return $q->where('status', 'belum_tebus'); }
    public function scopeSudahTebus($q) { return $q->where('status', 'sudah_tebus'); }
}
