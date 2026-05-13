<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Armada extends Model
{
    protected $fillable = [
        'no_polisi','jenis','no_rangka','no_mesin','tahun_pembuatan',
        'sopir_id','kernet_id','stnk_tahunan','stnk_5tahunan','is_active',
    ];

    protected $casts = [
        'is_active'       => 'boolean',
        'stnk_tahunan'    => 'date',
        'stnk_5tahunan'   => 'date',
    ];

    public function sopir()  { return $this->belongsTo(Karyawan::class, 'sopir_id'); }
    public function kernet() { return $this->belongsTo(Karyawan::class, 'kernet_id'); }

    public function scopeAktif($q) { return $q->where('is_active', true); }

    /** Cek notifikasi STNK jatuh tempo dalam 14 hari */
    public function getNotifikasiStnkAttribute(): ?array
    {
        $notif = [];
        $now   = now();
        $batas = 14;

        foreach (['stnk_tahunan' => 'Pajak Tahunan', 'stnk_5tahunan' => 'Pajak 5 Tahunan'] as $col => $label) {
            if (! $this->$col) continue;

            // Hitung jatuh tempo tahun ini
            $jatuhTempo = $this->$col->copy()->year($now->year);
            if ($jatuhTempo->isPast()) $jatuhTempo->addYear();

            $selisih = $now->diffInDays($jatuhTempo, false);
            if ($selisih >= 0 && $selisih <= $batas) {
                $notif[] = [
                    'label'        => $label,
                    'jatuh_tempo'  => $jatuhTempo->format('d/m/Y'),
                    'sisa_hari'    => $selisih,
                    'armada_id'    => $this->id,
                    'no_polisi'    => $this->no_polisi,
                    'tipe'         => $col,
                ];
            }
        }
        return empty($notif) ? null : $notif;
    }
}
