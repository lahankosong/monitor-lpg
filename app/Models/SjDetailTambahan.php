<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class SjDetailTambahan extends Model
{
    protected $table    = 'sj_detail_tambahan';
    protected $fillable = [
        'sj_detail_id','qty','sumber_tipe',
        'sumber_sj_detail_id','stok_armada_id','gudang_stok_id','keterangan',
    ];

    public function detail()       { return $this->belongsTo(SuratJalanDetail::class, 'sj_detail_id'); }
    public function sumberDetail() { return $this->belongsTo(SuratJalanDetail::class, 'sumber_sj_detail_id'); }
    public function stokArmada()   { return $this->belongsTo(StokArmada::class, 'stok_armada_id'); }
    public function gudangStok()   { return $this->belongsTo(GudangStok::class, 'gudang_stok_id'); }

    const LABEL = [
        'gendongan'             => '⚡ Gendongan',
        'gudang'                => '🏪 Gudang',
        'pengalihan_pangkalan'  => '↗ Pengalihan dari pangkalan lain',
    ];
}
