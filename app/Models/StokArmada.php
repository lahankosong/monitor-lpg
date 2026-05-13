<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StokArmada extends Model
{
    protected $table    = 'stok_armada';
    protected $fillable = [
        'armada_id','sj_header_id','tanggal',
        'gendongan_masuk','ambil_do','ambil_gudang',
        'total_terkirim','turun_gudang','sisa_akhir','status',
    ];
    protected $casts = ['tanggal' => 'date'];

    public function armada()   { return $this->belongsTo(Armada::class); }
    public function sjHeader() { return $this->belongsTo(SuratJalanHeader::class, 'sj_header_id'); }

    public function getTotalTersediaAttribute(): int
    {
        return $this->gendongan_masuk + $this->ambil_do + $this->ambil_gudang;
    }

    /** Cek apakah armada ini masih punya gendongan yang harus dihabiskan */
    public static function getGendonganAktif(int $armadaId): int
    {
        return static::where('armada_id', $armadaId)
            ->where('sisa_akhir', '>', 0)
            ->sum('sisa_akhir');
    }

    /** Hitung dan simpan sisa akhir setelah semua distribusi selesai */
    public function tutupTrip(): void
    {
        $sisaAkhir = $this->total_tersedia - $this->total_terkirim - $this->turun_gudang;
        $this->update([
            'sisa_akhir' => max(0, $sisaAkhir),
            'status'     => $sisaAkhir > 0 ? 'ada_sisa' : 'selesai',
        ]);
    }
}
