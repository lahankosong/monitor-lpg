<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class SuratJalanHeader extends Model
{
    protected $table = 'surat_jalan_headers';

    protected $fillable = [
        'no_sj','nomor_urut','no_lo','tanggal',
        'kitir_id','kitir_detail_id','armada_id','sopir_id','kernet_id',
        'total_kuota','total_terjadwal','qty_refil','qty_tabung_baru',
        'status','alasan_batal',
    ];

    protected $casts = ['tanggal' => 'date'];

    public function kitir()        { return $this->belongsTo(Kitir::class); }
    public function kitirDetail()  { return $this->belongsTo(KitirDetail::class); }
    public function armada()       { return $this->belongsTo(Armada::class); }
    public function sopir()        { return $this->belongsTo(Karyawan::class, 'sopir_id'); }
    public function kernet()       { return $this->belongsTo(Karyawan::class, 'kernet_id'); }
    public function details()      { return $this->hasMany(SuratJalanDetail::class, 'header_id')->orderBy('urutan'); }
    public function stokArmada()   { return $this->hasMany(StokArmada::class, 'sj_header_id'); }

    /**
     * Generate nomor SJ otomatis
     * Format: {KODE_AGEN}-{YYMMDD}-{NO_POLISI_TANPA_SPASI}-{URUTAN}
     * Contoh: MGE-260508-R8464IH-01
     */
    public static function generateNomor(string $tanggal, string $noPolisi): string
    {
        $agen      = Agen::profil();
        $kodeAgen  = strtoupper($agen?->kode_agen ?? 'AGN');
        $tgl       = Carbon::parse($tanggal)->format('ymd');
        $plat      = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $noPolisi));

        // Hitung urutan SJ yang sudah ada pada hari & armada ini
        $urutan = static::where('tanggal', $tanggal)
                ->where('armada_id', function($q) use ($noPolisi) {
                    $q->select('id')->from('armadas')->where('no_polisi', $noPolisi)->limit(1);
                })->whereNotIn('status', ['batal'])
                ->count() + 1;

        return sprintf('%s-%s-%s-%02d', $kodeAgen, $tgl, $plat, $urutan);
    }

    public static function generateNomorByArmadaId(string $tanggal, int $armadaId): array
    {
        $agen     = Agen::profil();
        $kodeAgen = strtoupper($agen?->kode_agen ?? 'AGN');
        $tgl      = Carbon::parse($tanggal)->format('ymd');
        $armada   = Armada::find($armadaId);
        $plat     = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $armada?->no_polisi ?? ''));

        // Hitung SEMUA SJ pada hari itu (termasuk batal) untuk nomor urut
        $urutan = static::where('tanggal', $tanggal)->count() + 1;

        $noSj = sprintf('%s-%s-%s-%02d', $kodeAgen, $tgl, $plat, $urutan);

        return ['no_sj' => $noSj, 'nomor_urut' => $urutan];
    }

    public function scopeAktif($q)  { return $q->where('status', 'aktif'); }
    public function scopeSelesai($q){ return $q->where('status', 'selesai'); }

    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'draft'   => 'Draft',
            'aktif'   => 'Aktif',
            'selesai' => 'Selesai',
            'batal'   => 'Dibatalkan',
            default   => ucfirst($this->status),
        };
    }

    public function getTotalTerkirimAttribute(): int
    {
        return $this->details->sum('qty_terima');
    }

    public function getSisaDistribusiAttribute(): int
    {
        return $this->total_kuota - $this->total_terkirim;
    }
}
