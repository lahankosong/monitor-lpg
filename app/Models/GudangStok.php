<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GudangStok extends Model
{
    protected $table    = 'gudang_stok';
    protected $fillable = [
        'agen_id','sumber','sj_header_id','agen_asal_id',
        'tgl_masuk','qty_masuk','qty_keluar','sisa_stok','keterangan',
    ];
    protected $casts = ['tgl_masuk' => 'date'];

    public function agen()      { return $this->belongsTo(Agen::class); }
    public function agenAsal()  { return $this->belongsTo(Agen::class, 'agen_asal_id'); }
    public function sjHeader()  { return $this->belongsTo(SuratJalanHeader::class, 'sj_header_id'); }

    /** Total stok tersedia di gudang agen ini */
    public static function totalTersedia(int $agenId): int
    {
        return static::where('agen_id', $agenId)
            ->where('sisa_stok', '>', 0)
            ->sum('sisa_stok');
    }

    /** Kurangi stok gudang (FIFO) */
    public static function kurangi(int $agenId, int $qty, string $keterangan = ''): bool
    {
        $stoks = static::where('agen_id', $agenId)
            ->where('sisa_stok', '>', 0)
            ->orderBy('tgl_masuk')
            ->get();

        $sisa = $qty;
        foreach ($stoks as $s) {
            if ($sisa <= 0) break;
            $ambil    = min($s->sisa_stok, $sisa);
            $s->qty_keluar += $ambil;
            $s->sisa_stok  -= $ambil;
            $s->save();
            $sisa -= $ambil;
        }
        return $sisa === 0;
    }
}
