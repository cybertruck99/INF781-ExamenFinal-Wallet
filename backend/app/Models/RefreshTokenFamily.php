<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;

class RefreshTokenFamily extends Model
{
    use HasPublicUuid;

    protected $fillable = ['uuid', 'user_id', 'revoked_at'];

    protected function casts(): array
    {
        return ['revoked_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function refreshTokens()
    {
        return $this->hasMany(RefreshToken::class, 'family_id');
    }
}
