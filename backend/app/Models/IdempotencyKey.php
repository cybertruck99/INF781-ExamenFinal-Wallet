<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;

class IdempotencyKey extends Model
{
    use HasPublicUuid;

    protected $fillable = ['uuid', 'user_id', 'key', 'request_hash', 'transfer_id', 'response_json', 'status_code', 'expires_at'];

    protected function casts(): array
    {
        return ['response_json' => 'array', 'expires_at' => 'datetime', 'status_code' => 'integer'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transfer()
    {
        return $this->belongsTo(Transfer::class);
    }
}
