<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;

class AccessToken extends Model
{
    use HasPublicUuid;

    protected $fillable = ['uuid', 'user_id', 'family_id', 'token_hash', 'expires_at', 'revoked_at', 'ip', 'user_agent'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function family()
    {
        return $this->belongsTo(RefreshTokenFamily::class, 'family_id');
    }
}
