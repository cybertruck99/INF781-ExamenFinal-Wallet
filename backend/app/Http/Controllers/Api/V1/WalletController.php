<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\FormatsResponses;
use App\Http\Controllers\Controller;
use App\Http\Requests\Wallet\TopupRequest;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Services\AuditLogger;
use App\Services\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WalletController extends Controller
{
    use FormatsResponses;

    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function show(Request $request): JsonResponse
    {
        return response()->json(['wallet' => $this->walletPayload($request->user())]);
    }

    public function topup(TopupRequest $request): JsonResponse
    {
        $amountCents = Money::toCents($request->input('monto'));
        $description = $request->string('descripcion')->trim()->toString() ?: 'Recarga';
        $user = $request->user();

        $transaction = DB::transaction(function () use ($user, $amountCents, $description) {
            /** @var Wallet $wallet */
            $wallet = Wallet::query()->where('user_id', $user->id)->lockForUpdate()->firstOrFail();
            $wallet->increment('balance_cents', $amountCents);
            $wallet->refresh();

            return Transaction::query()->create([
                'user_id' => $user->id,
                'type' => 'RECARGA',
                'amount_cents' => $amountCents,
                'description' => $description,
                'balance_after_cents' => $wallet->balance_cents,
                'created_at' => now(),
            ]);
        });

        $this->audit->log($request, 'WALLET_TOPUP', $user, ['amount_cents' => $amountCents]);

        return response()->json([
            'message' => 'Saldo recargado correctamente.',
            'transaction' => $this->transactionPayload($transaction),
            'wallet' => $this->walletPayload($user->fresh('wallet')),
        ], 201);
    }
}
