<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, HasPublicUuid, Notifiable;

    protected $fillable = [
        'uuid', 'full_name', 'ci', 'email', 'phone', 'password', 'role',
        'is_blocked', 'blocked_until', 'failed_login_attempts',
        'mfa_enabled', 'mfa_secret', 'mfa_pending_secret',
    ];

    protected $hidden = [
        'password', 'mfa_secret', 'mfa_pending_secret', 'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_blocked' => 'boolean',
            'blocked_until' => 'datetime',
            'mfa_enabled' => 'boolean',
        ];
    }

    public function wallet()
    {
        return $this->hasOne(Wallet::class);
    }

    public function accessTokens()
    {
        return $this->hasMany(AccessToken::class);
    }

    public function refreshTokenFamilies()
    {
        return $this->hasMany(RefreshTokenFamily::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'ADMIN';
    }

    public function isTemporarilyBlocked(): bool
    {
        return (bool) $this->is_blocked || ($this->blocked_until && $this->blocked_until->isFuture());
    }
}
