<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class TebusanKitirDetail extends Model
{
    protected $fillable = ['tebusan_id','kitir_detail_id','jumlah_tabung','subtotal'];
    public function tebusan()    { return $this->belongsTo(TebusanKitir::class); }
    public function kitirDetail(){ return $this->belongsTo(KitirDetail::class); }
}
