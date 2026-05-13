<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransaksiAntarAgen extends Model
{
    protected $table    = 'transaksi_antar_agen';
    protected $fillable = [
        'agen_asal_id','agen_tujuan_id',
        'qty_tabung_isi','qty_tabung_kosong',
        'tgl_titip','tgl_ambil_kembali','status',
        'gudang_stok_id','sj_header_id','keterangan',
    ];
    protected $casts = [
        'tgl_titip'          => 'date',
        'tgl_ambil_kembali'  => 'date',
    ];

    public function agenAsal()   { return $this->belongsTo(Agen::class, 'agen_asal_id'); }
    public function agenTujuan() { return $this->belongsTo(Agen::class, 'agen_tujuan_id'); }
    public function gudangStok() { return $this->belongsTo(GudangStok::class); }
    public function sjHeader()   { return $this->belongsTo(SuratJalanHeader::class, 'sj_header_id'); }
}
