<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;

class MfaTicket extends Model
{
    use HasPublicUuid;

    protected $fillable = ['uuid', 'user_id', 'ticket_hash', 'expires_at', 'used_at', 'ip', 'user_agent'];

    protected $hidden = ['ticket_hash'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'used_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
