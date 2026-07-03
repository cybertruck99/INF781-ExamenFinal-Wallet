<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\FormatsResponses;
use App\Http\Controllers\Api\V1\Concerns\ValidatesQueryParameters;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BlockUserRequest;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    use FormatsResponses, ValidatesQueryParameters;

    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function users(Request $request): JsonResponse
    {
        $data = $this->validateQuery($request, [
            'q' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $query = User::query()->with('wallet')->orderBy('id');
        if (! empty($data['q'])) {
            $q = '%'.$data['q'].'%';
            $query->where(function ($sub) use ($q) {
                $sub->where('full_name', 'like', $q)
                    ->orWhere('email', 'like', $q)
                    ->orWhere('phone', 'like', $q);
            });
        }

        $page = $query->paginate($data['per_page'] ?? 15);

        return response()->json([
            'data' => $page->getCollection()->map(function (User $user) {
                $payload = $this->publicUser($user);
                $payload['wallet_balance'] = $user->wallet ? \App\Services\Money::toDecimal($user->wallet->balance_cents) : '0.00';
                return $payload;
            })->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    public function block(BlockUserRequest $request, string $uuid): JsonResponse
    {
        /** @var User|null $target */
        $target = User::query()->where('uuid', $uuid)->first();
        if (! $target) {
            abort(404);
        }

        if ($target->id === $request->user()->id) {
            abort(422, 'El administrador no puede bloquearse a sí mismo desde este endpoint.');
        }

        $blocked = $request->boolean('blocked');
        $target->forceFill([
            'is_blocked' => $blocked,
            'blocked_until' => $blocked && $request->filled('minutes') ? now()->addMinutes((int) $request->integer('minutes')) : null,
            'failed_login_attempts' => 0,
        ])->save();

        $this->audit->log($request, $blocked ? 'ADMIN_USER_BLOCKED' : 'ADMIN_USER_UNBLOCKED', $request->user(), [
            'target_uuid' => $target->uuid,
        ]);

        return response()->json(['user' => $this->publicUser($target->fresh())]);
    }

    public function auditLogs(Request $request): JsonResponse
    {
        $data = $this->validateQuery($request, [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'action' => ['nullable', 'string', 'max:80'],
        ]);

        $query = AuditLog::query()->with('user')->orderByDesc('created_at');
        if (! empty($data['action'])) {
            $query->where('action', $data['action']);
        }

        $page = $query->paginate($data['per_page'] ?? 20);

        return response()->json([
            'data' => $page->getCollection()->map(fn (AuditLog $log) => [
                'uuid' => $log->uuid,
                'action' => $log->action,
                'user' => $log->user ? [
                    'uuid' => $log->user->uuid,
                    'email' => $log->user->email,
                    'role' => $log->user->role,
                ] : null,
                'ip' => $log->ip,
                'user_agent' => $log->user_agent,
                'metadata' => $log->metadata,
                'created_at' => $log->created_at?->toIso8601String(),
            ])->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }
}
