<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class TebusanKitir extends Model
{
    protected $fillable = [
        'kitir_id','tanggal_bayar','jumlah_tabung_ditebus',
        'total_bayar','selisih_pembulatan','total_bayar_aktual',
        'no_rekening_tujuan','bukti_transfer','keterangan','created_by',
    ];
    protected $casts = ['tanggal_bayar' => 'date'];

    public function kitir()    { return $this->belongsTo(Kitir::class); }
    public function details()  { return $this->hasMany(TebusanKitirDetail::class, 'tebusan_id'); }
    public function createdBy(){ return $this->belongsTo(User::class, 'created_by'); }
}
