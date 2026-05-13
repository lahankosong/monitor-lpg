<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Karyawan extends Model
{
    protected $fillable = ['nama_karyawan','role','telepon','is_active'];
    protected $casts    = ['is_active' => 'boolean'];

    const ROLES = [
        'owner'     => 'Owner',
        'direktur'  => 'Direktur',
        'manager'   => 'Manager',
        'admin'     => 'Admin',
        'driver'    => 'Sopir/Driver',
        'co-driver' => 'Kernet/Co-Driver',
        'security'  => 'Security',
    ];

    public function armadaSopir()  { return $this->hasOne(Armada::class, 'sopir_id'); }
    public function armadaKernet() { return $this->hasOne(Armada::class, 'kernet_id'); }

    public function scopeDriver($q)    { return $q->where('role', 'driver'); }
    public function scopeKernet($q)    { return $q->where('role', 'co-driver'); }
    public function scopeAktif($q)     { return $q->where('is_active', true); }

    public function getRoleLabelAttribute(): string
    {
        return self::ROLES[$this->role] ?? ucfirst($this->role);
    }
}
