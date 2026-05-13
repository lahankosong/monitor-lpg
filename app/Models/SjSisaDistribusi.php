<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SjSisaDistribusi extends Model
{
    protected $table    = 'sj_sisa_distribusi';
    protected $fillable = [
        'sj_detail_id','qty','tipe',
        'referensi_id','referensi_tipe','keterangan',
    ];

    public function detail() { return $this->belongsTo(SuratJalanDetail::class, 'sj_detail_id'); }

    public function pengalihan() { return $this->belongsTo(SjPengalihan::class, 'referensi_id'); }
    public function gudang()     { return $this->belongsTo(GudangStok::class, 'referensi_id'); }
    public function transaksi()  { return $this->belongsTo(TransaksiAntarAgen::class, 'referensi_id'); }
    public function stok()       { return $this->belongsTo(StokArmada::class, 'referensi_id'); }

    const TIPE_LABEL = [
        'alih_pangkalan'  => '↗ Dialihkan ke Pangkalan',
        'stok_armada'     => '⚡ Tetap di Armada (Gendongan)',
        'gudang_sendiri'  => '🏪 Turun ke Gudang',
        'titip_agen_lain' => '🤝 Titip Gudang Agen Lain',
    ];
}
