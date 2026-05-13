<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class DistribusiRealisasi extends Model
{
    protected $table    = 'distribusi_realisasis';
    protected $fillable = [
        'sj_detail_id','header_id','pangkalan_id',
        'qty_jadwal','qty_terima','foto_bukti',
        'status_sisa','alih_ke_pangkalan_id',
        'tanggal_realisasi','keterangan','dilaporkan_oleh',
    ];
    protected $casts = ['tanggal_realisasi' => 'date'];

    public function header()     { return $this->belongsTo(SuratJalanHeader::class, 'header_id'); }
    public function sjDetail()   { return $this->belongsTo(SuratJalanDetail::class, 'sj_detail_id'); }
    public function pangkalan()  { return $this->belongsTo(Pangkalan::class); }
    public function alihKe()     { return $this->belongsTo(Pangkalan::class, 'alih_ke_pangkalan_id'); }
    public function pelapor()    { return $this->belongsTo(\App\Models\User::class, 'dilaporkan_oleh'); }

    public function getQtySisaAttribute(): int
    {
        return $this->qty_jadwal - $this->qty_terima;
    }

    const STATUS_SISA = [
        'lunas'         => 'Lunas (terkirim semua)',
        'gendongan'     => 'Sisa dibawa kembali (gendongan)',
        'gudang'        => 'Sisa disimpan di gudang',
        'alih_pangkalan'=> 'Dialihkan ke pangkalan lain',
    ];
}
