<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Contracts\Encryption\DecryptException;

class PangkalanSession extends Model
{
    protected $fillable = [
        'pangkalan_id',
        'label',
        'username',
        'password_encrypted',
        'registration_id',
        'is_active',
        'last_login_at',
    ];

    protected $casts = [
        'is_active'     => 'boolean',
        'last_login_at' => 'datetime',
    ];

    /**
     * Getter: dekripsi password otomatis saat akses $model->password
     */
    public function getPasswordAttribute(): ?string
    {
        if (empty($this->password_encrypted)) return null;

        try {
            return Crypt::decryptString($this->password_encrypted);
        } catch (DecryptException $e) {
            return null;
        }
    }

    /**
     * Setter: enkripsi otomatis saat set $model->password = '...'
     */
    public function setPasswordAttribute(string $value): void
    {
        $this->attributes['password_encrypted'] = Crypt::encryptString($value);
    }

    public function token()
    {
        return $this->hasOne(PangkalanToken::class, 'pangkalan_id', 'pangkalan_id');
    }
}