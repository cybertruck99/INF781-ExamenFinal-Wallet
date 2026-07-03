<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;

class RefreshToken extends Model
{
    use HasPublicUuid;

    protected $fillable = ['uuid', 'family_id', 'user_id', 'token_hash', 'expires_at', 'used_at', 'revoked_at', 'ip', 'user_agent'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'used_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function family()
    {
        return $this->belongsTo(RefreshTokenFamily::class, 'family_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
