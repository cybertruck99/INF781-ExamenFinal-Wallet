<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Throwable;

class AuditLogger
{
    public function log(Request $request, string $action, ?User $user = null, array $metadata = []): void
    {
        try {
            AuditLog::query()->create([
                'user_id' => $user?->id,
                'action' => $action,
                'ip' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
                'metadata' => $metadata,
                'created_at' => now(),
            ]);
        } catch (Throwable) {
            // La bitácora no debe romper el flujo principal. El error quedará en logs del sistema.
        }
    }
}
