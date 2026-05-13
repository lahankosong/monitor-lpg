<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SjPengalihan extends Model
{
    protected $table    = 'sj_pengalihan';
    protected $fillable = [
        'sj_detail_id','pangkalan_id','qty',
        'qty_terima_aktual','urutan','status','keterangan',
    ];

    public function detail()    { return $this->belongsTo(SuratJalanDetail::class, 'sj_detail_id'); }
    public function pangkalan() { return $this->belongsTo(Pangkalan::class); }
}
