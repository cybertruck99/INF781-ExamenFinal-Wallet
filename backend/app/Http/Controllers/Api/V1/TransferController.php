<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\FormatsResponses;
use App\Http\Controllers\Controller;
use App\Http\Requests\Transfer\ConfirmTransferRequest;
use App\Http\Requests\Transfer\CreateTransferRequest;
use App\Models\IdempotencyKey;
use App\Models\Transaction;
use App\Models\Transfer;
use App\Models\User;
use App\Models\Wallet;
use App\Services\AuditLogger;
use App\Services\Money;
use App\Services\TotpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TransferController extends Controller
{
    use FormatsResponses;

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly TotpService $totp,
    ) {
    }

    public function store(CreateTransferRequest $request): JsonResponse
    {
        $user = $request->user();
        $idempotencyKey = (string) $request->header('Idempotency-Key', '');
        if (! Str::isUuid($idempotencyKey)) {
            abort(422, 'Idempotency-Key obligatorio y debe ser UUID.');
        }

        $bodyHash = hash('sha256', json_encode($request->only(['destinatario', 'monto', 'descripcion']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return DB::transaction(function () use ($request, $user, $idempotencyKey, $bodyHash) {
            $existing = IdempotencyKey::query()
                ->where('user_id', $user->id)
                ->where('key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                if (! hash_equals($existing->request_hash, $bodyHash)) {
                    abort(409, 'Idempotency-Key reutilizada con un cuerpo distinto.');
                }

                return response()->json($existing->response_json, $existing->status_code);
            }

            $recipientIdentity = $request->string('destinatario')->trim()->toString();
            /** @var User|null $recipient */
            $recipient = User::query()
                ->where('email', strtolower($recipientIdentity))
                ->orWhere('phone', $recipientIdentity)
                ->first();

            if (! $recipient || $recipient->id === $user->id) {
                abort(422, 'Destinatario inválido.');
            }

            if ($recipient->isTemporarilyBlocked()) {
                abort(422, 'El destinatario no puede recibir transferencias en este momento.');
            }

            $amountCents = Money::toCents($request->input('monto'));
            /** @var Wallet $wallet */
            $wallet = Wallet::query()->where('user_id', $user->id)->lockForUpdate()->firstOrFail();
            if ($wallet->balance_cents < $amountCents) {
                abort(409, 'Saldo insuficiente.');
            }

            $transfer = Transfer::query()->create([
                'sender_user_id' => $user->id,
                'recipient_user_id' => $recipient->id,
                'amount_cents' => $amountCents,
                'description' => $request->string('descripcion')->trim()->toString(),
                'status' => Transfer::PENDING,
                'requires_totp' => $amountCents > 50000,
                'expires_at' => now()->addSeconds(config('securewallet.pending_transfer_ttl_seconds', 120)),
            ])->load('recipient');

            $payload = $this->transferPayload($transfer);
            IdempotencyKey::query()->create([
                'user_id' => $user->id,
                'key' => $idempotencyKey,
                'request_hash' => $bodyHash,
                'transfer_id' => $transfer->id,
                'response_json' => $payload,
                'status_code' => 201,
                'expires_at' => now()->addDay(),
            ]);

            $this->audit->log($request, 'TRANSFER_CREATED', $user, [
                'transfer_uuid' => $transfer->uuid,
                'amount_cents' => $amountCents,
                'recipient_uuid' => $recipient->uuid,
            ]);

            return response()->json($payload, 201);
        });
    }

    public function confirm(ConfirmTransferRequest $request, string $uuid): JsonResponse
    {
        $user = $request->user();

        return DB::transaction(function () use ($request, $user, $uuid) {
            /** @var Transfer|null $transfer */
            $transfer = Transfer::query()
                ->where('uuid', $uuid)
                ->where('sender_user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if (! $transfer) {
                abort(404);
            }

            if ($transfer->status !== Transfer::PENDING) {
                abort(409, 'La transferencia ya no está pendiente.');
            }

            if ($transfer->expires_at->isPast()) {
                $transfer->forceFill(['status' => Transfer::EXPIRED])->save();
                abort(409, 'La transferencia expiró.');
            }

            if ($transfer->requires_totp) {
                if (! $user->mfa_enabled || ! $request->filled('totp_code')) {
                    abort(422, 'Esta transferencia requiere código TOTP.');
                }
                $secret = Crypt::decryptString($user->mfa_secret);
                if (! $this->totp->verify($secret, $request->string('totp_code')->toString())) {
                    $this->audit->log($request, 'TRANSFER_TOTP_FAILED', $user, ['transfer_uuid' => $transfer->uuid]);
                    abort(401);
                }
            }

            $wallets = Wallet::query()
                ->whereIn('user_id', [$transfer->sender_user_id, $transfer->recipient_user_id])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('user_id');

            /** @var Wallet $senderWallet */
            $senderWallet = $wallets[$transfer->sender_user_id];
            /** @var Wallet $recipientWallet */
            $recipientWallet = $wallets[$transfer->recipient_user_id];

            if ($senderWallet->balance_cents < $transfer->amount_cents) {
                abort(409, 'Saldo insuficiente.');
            }

            $senderWallet->balance_cents -= $transfer->amount_cents;
            $recipientWallet->balance_cents += $transfer->amount_cents;
            $senderWallet->save();
            $recipientWallet->save();

            Transaction::query()->create([
                'user_id' => $transfer->sender_user_id,
                'transfer_id' => $transfer->id,
                'type' => 'ENVIO',
                'amount_cents' => $transfer->amount_cents,
                'counterparty_user_id' => $transfer->recipient_user_id,
                'description' => $transfer->description,
                'balance_after_cents' => $senderWallet->balance_cents,
                'created_at' => now(),
            ]);

            Transaction::query()->create([
                'user_id' => $transfer->recipient_user_id,
                'transfer_id' => $transfer->id,
                'type' => 'RECEPCION',
                'amount_cents' => $transfer->amount_cents,
                'counterparty_user_id' => $transfer->sender_user_id,
                'description' => $transfer->description,
                'balance_after_cents' => $recipientWallet->balance_cents,
                'created_at' => now(),
            ]);

            $transfer->forceFill([
                'status' => Transfer::CONFIRMED,
                'confirmed_at' => now(),
            ])->save();

            $this->audit->log($request, 'TRANSFER_CONFIRMED', $user, [
                'transfer_uuid' => $transfer->uuid,
                'amount_cents' => $transfer->amount_cents,
            ]);

            return response()->json([
                'message' => 'Transferencia confirmada correctamente.',
                'transfer' => $this->transferPayload($transfer->fresh(['recipient'])),
                'wallet' => $this->walletPayload($user->fresh('wallet')),
            ]);
        });
    }
}
