<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasPublicUuid;

    public const UPDATED_AT = null;

    protected $fillable = ['uuid', 'user_id', 'action', 'ip', 'user_agent', 'metadata', 'created_at'];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'created_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
