<?php
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name','email','password',
        'role','is_active','last_login_at','karyawan_id',
    ];

    protected $hidden = ['password','remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at'     => 'datetime',
        'is_active'         => 'boolean',
        'password'          => 'hashed',
    ];

    // ── Role helpers ───────────────────────────────────────────────
    public function isDirektur(): bool { return $this->role === 'direktur'; }
    public function isManajer(): bool  { return $this->role === 'manajer'; }
    public function isAdmin(): bool    { return $this->role === 'admin'; }
    public function isDriver(): bool   { return $this->role === 'driver'; }

    /** Bisa akses semua menu operasional */
    public function canOperate(): bool { return in_array($this->role, ['direktur','manajer','admin']); }

    /** Bisa lihat laporan keuangan */
    public function canViewFinance(): bool { return in_array($this->role, ['direktur','manajer']); }

    const ROLE_LABEL = [
        'direktur' => 'Direktur',
        'manajer'  => 'Manajer',
        'admin'    => 'Admin',
        'driver'   => 'Driver',
    ];

    const ROLE_BADGE_COLOR = [
        'direktur' => 'background:#FEE2E2;color:#991B1B',
        'manajer'  => 'background:#DBEAFE;color:#1E40AF',
        'admin'    => 'background:#D1FAE5;color:#065F46',
        'driver'   => 'background:#FEF3C7;color:#92400E',
    ];

    public function getRoleLabelAttribute(): string
    {
        return self::ROLE_LABEL[$this->role] ?? ucfirst($this->role);
    }

    public function karyawan() { return $this->belongsTo(Karyawan::class); }
}
