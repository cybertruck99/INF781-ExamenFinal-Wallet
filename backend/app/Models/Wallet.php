<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{
    use HasPublicUuid;

    protected $fillable = ['uuid', 'user_id', 'balance_cents'];

    protected function casts(): array
    {
        return ['balance_cents' => 'integer'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
