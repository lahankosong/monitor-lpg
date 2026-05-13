<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use App\Models\SjDetailTambahan;

class SuratJalanDetail extends Model
{
    protected $table = 'surat_jalan_details';

    protected $fillable = [
        'header_id','pangkalan_id','qty_jadwal','qty_terima',
        'urutan','status','qty_dialihkan','dialih_ke_pangkalan_id','keterangan',
    ];

    public function header()           { return $this->belongsTo(SuratJalanHeader::class, 'header_id'); }
    public function pangkalan()        { return $this->belongsTo(Pangkalan::class); }
    public function dialihKe()         { return $this->belongsTo(Pangkalan::class, 'dialih_ke_pangkalan_id'); }
    public function sisaDistribusi()   { return $this->hasMany(SjSisaDistribusi::class, 'sj_detail_id'); }
    public function tambahan()         { return $this->hasMany(SjDetailTambahan::class, 'sj_detail_id'); }

    /** Qty maksimum yang boleh diterima = jadwal + semua tambahan yang sudah dikonfirmasi */
    public function getQtyMaksAttribute(): int
    {
        // Kolom DB sudah terisi → pakai itu
        $maks = (int)($this->attributes['qty_maks'] ?? 0);
        if ($maks > 0) return $maks;

        // Fallback: hitung langsung dari relasi tambahan
        if ($this->relationLoaded('tambahan') && $this->tambahan->isNotEmpty()) {
            return $this->qty_jadwal + $this->tambahan->sum('qty');
        }

        // Query ke DB jika relasi belum di-load
        $totalTambahan = SjDetailTambahan::where('sj_detail_id', $this->id)->sum('qty');
        return $this->qty_jadwal + $totalTambahan;
    }

    public function getQtyTambahanTotalAttribute(): int
    {
        return max(0, $this->qty_maks - $this->qty_jadwal);
    }
    public function pengalihanList()   { return $this->hasMany(SjPengalihan::class, 'sj_detail_id')->orderBy('urutan'); }

    /** Qty sudah terkirim + sudah dialihkan = tidak lagi ada di armada */
    public function getSudahDisalurkanAttribute(): int
    {
        return $this->qty_terima + $this->pengalihan->sum('qty');
    }
    /** Sisa yang masih di armada (gendongan potensial) */
    public function getSisaArmadaAttribute(): int
    {
        return max(0, $this->qty_jadwal - $this->sudah_disalurkan);
    }

    public function getSisaAttribute(): int
    {
        return $this->qty_jadwal - $this->qty_terima - $this->qty_dialihkan;
    }
}
