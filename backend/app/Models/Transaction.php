<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasPublicUuid;

    public $timestamps = false;

    protected $fillable = [
        'uuid', 'user_id', 'transfer_id', 'type', 'amount_cents',
        'counterparty_user_id', 'description', 'balance_after_cents', 'created_at'
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'balance_after_cents' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function counterparty()
    {
        return $this->belongsTo(User::class, 'counterparty_user_id');
    }

    public function transfer()
    {
        return $this->belongsTo(Transfer::class);
    }
}
