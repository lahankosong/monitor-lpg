<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class HargaReferensi extends Model
{
    protected $table    = 'harga_referensis';
    protected $fillable = [
        'nama_item','kategori','harga','satuan',
        'berlaku_mulai','berlaku_sampai','keterangan','is_active',
    ];

    protected $casts = [
        'berlaku_mulai'  => 'date',
        'berlaku_sampai' => 'date',
        'is_active'      => 'boolean',
    ];

    /** Ambil harga aktif per kategori pada tanggal tertentu */
    public static function hargaAktif(string $kategori, ?string $tanggal = null): ?self
    {
        $tgl = $tanggal ?? now()->toDateString();
        return static::where('kategori', $kategori)
            ->where('is_active', true)
            ->where('berlaku_mulai', '<=', $tgl)
            ->where(fn($q) => $q->whereNull('berlaku_sampai')->orWhere('berlaku_sampai', '>=', $tgl))
            ->orderByDesc('berlaku_mulai')
            ->first();
    }

    public function getKategoriLabelAttribute(): string
    {
        return match($this->kategori) {
            'tebus_refil'    => 'Harga Tebus Refil',
            'jual_pangkalan' => 'Harga Jual ke Pangkalan',
            'tabung_perdana' => 'Harga Tabung Perdana',
            'sewa_tabung'    => 'Harga Sewa Tabung',
            default          => 'Lainnya',
        };
    }
}
