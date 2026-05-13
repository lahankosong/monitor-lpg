<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class JurnalAkuntansi extends Model
{
    protected $table    = 'jurnal_akuntansis';
    protected $fillable = [
        'tanggal','modul','jenis','jumlah','keterangan','referensi','created_by',
    ];
    protected $casts = ['tanggal' => 'date'];

    public function createdBy() { return $this->belongsTo(User::class, 'created_by'); }

    const MODUL_LABELS = [
        'tebusan'           => 'Tebusan SPBE',
        'penjualan'         => 'Penjualan Refil',
        'operasional_gaji'  => 'Gaji Karyawan',
        'operasional_armada'=> 'Operasional Armada',
        'operasional_kantor'=> 'Operasional Kantor',
        'lain_lain'         => 'Lain-lain',
        'modal'             => 'Modal / Penarikan',
    ];

    public static function saldoBulan(string $modul, int $bulan, int $tahun): array
    {
        $data = static::where('modul', $modul)
            ->whereYear('tanggal', $tahun)
            ->whereMonth('tanggal', $bulan)
            ->selectRaw("jenis, SUM(jumlah) as total")
            ->groupBy('jenis')
            ->get()->keyBy('jenis');

        $masuk  = $data->get('masuk')?->total ?? 0;
        $keluar = $data->get('keluar')?->total ?? 0;
        return ['masuk' => $masuk, 'keluar' => $keluar, 'saldo' => $masuk - $keluar];
    }
}
