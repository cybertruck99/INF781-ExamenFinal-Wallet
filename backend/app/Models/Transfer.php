<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;

class Transfer extends Model
{
    use HasPublicUuid;

    public const PENDING = 'PENDIENTE_CONFIRMACION';
    public const CONFIRMED = 'CONFIRMADA';
    public const EXPIRED = 'EXPIRADA';
    public const CANCELLED = 'CANCELADA';

    protected $fillable = [
        'uuid', 'sender_user_id', 'recipient_user_id', 'amount_cents',
        'description', 'status', 'requires_totp', 'expires_at', 'confirmed_at'
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'requires_totp' => 'boolean',
            'expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }

    public function recipient()
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }
}
