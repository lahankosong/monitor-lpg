<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class Pangkalan extends Model
{
    protected $fillable = [
        'no_reg','nama_pangkalan','nama_pemilik','nik_pemilik','foto_ktp_path','alamat_pemilik',
        'alamat','telepon','tipe','no_registrasi',
        'alokasi_per_bulan',
        'jumlah_tabung_pinjaman','harga_sewa_per_tabung','tanggal_mulai_pinjaman',
        'jangka_pinjaman_bulan','nomor_bukti_pinjaman',
        'latitude','longitude',
        'map_email','map_pin_encrypted','map_pangkalan_id',
        'is_active',
    ];

    protected $casts = [
        'is_active'              => 'boolean',
        'tanggal_mulai_pinjaman' => 'date',
        'latitude'               => 'decimal:7',
        'longitude'              => 'decimal:7',
    ];

    // Getter PIN MAP terdeskirpsi
    public function getMapPinAttribute(): ?string
    {
        if (empty($this->map_pin_encrypted)) return null;
        try { return Crypt::decryptString($this->map_pin_encrypted); }
        catch (\Exception $e) { return null; }
    }

    public function setMapPinAttribute(string $value): void
    {
        $this->attributes['map_pin_encrypted'] = Crypt::encryptString($value);
    }

    public function scopeAktif($q)       { return $q->where('is_active', true); }
    public function scopeAlphabetical($q){ return $q->orderBy('nama_pangkalan'); }
    public function scopeKerjasama($q)   { return $q->where('tipe', 'kerjasama'); }
    public function scopeWithMap($q)     { return $q->whereNotNull('map_email'); }
}
