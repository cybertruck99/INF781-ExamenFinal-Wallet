<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Models\Transaction;
use App\Models\Transfer;
use App\Models\User;
use App\Services\Money;

trait FormatsResponses
{
    protected function publicUser(User $user): array
    {
        return [
            'uuid' => $user->uuid,
            'full_name' => $user->full_name,
            'ci' => $user->ci,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role,
            'is_blocked' => $user->is_blocked,
            'blocked_until' => $user->blocked_until?->toIso8601String(),
            'mfa_enabled' => (bool) $user->mfa_enabled,
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }

    protected function walletPayload(User $user): array
    {
        $wallet = $user->wallet()->firstOrFail();
        return [
            'uuid' => $wallet->uuid,
            'balance' => Money::toDecimal($wallet->balance_cents),
            'currency' => 'BOB',
            'owner' => [
                'uuid' => $user->uuid,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'phone' => $user->phone,
            ],
        ];
    }

    protected function transactionPayload(Transaction $tx): array
    {
        return [
            'uuid' => $tx->uuid,
            'type' => $tx->type,
            'amount' => Money::toDecimal($tx->amount_cents),
            'counterparty' => $tx->counterparty ? [
                'uuid' => $tx->counterparty->uuid,
                'full_name' => $tx->counterparty->full_name,
                'email' => $tx->counterparty->email,
                'phone' => $tx->counterparty->phone,
            ] : null,
            'description' => $tx->description,
            'balance_after' => Money::toDecimal($tx->balance_after_cents),
            'created_at' => $tx->created_at?->toIso8601String(),
        ];
    }

    protected function transferPayload(Transfer $transfer): array
    {
        return [
            'uuid' => $transfer->uuid,
            'estado' => $transfer->status,
            'monto' => Money::toDecimal($transfer->amount_cents),
            'requiere_totp' => (bool) $transfer->requires_totp,
            'expira_en' => max(0, now()->diffInSeconds($transfer->expires_at, false)),
            'destinatario' => [
                'uuid' => $transfer->recipient->uuid,
                'full_name' => $transfer->recipient->full_name,
                'email' => $transfer->recipient->email,
                'phone' => $transfer->recipient->phone,
            ],
        ];
    }
}
