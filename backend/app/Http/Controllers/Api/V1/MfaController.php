<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\MfaEnableConfirmRequest;
use App\Services\AuditLogger;
use App\Services\TotpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class MfaController extends Controller
{
    public function __construct(
        private readonly TotpService $totp,
        private readonly AuditLogger $audit,
    ) {
    }

    public function enable(Request $request): JsonResponse
    {
        $user = $request->user();
        $secret = $this->totp->generateSecret();

        $user->forceFill([
            'mfa_pending_secret' => Crypt::encryptString($secret),
        ])->save();

        $this->audit->log($request, 'MFA_ENABLE_STARTED', $user);

        return response()->json([
            'message' => 'Escanea este URI con Google Authenticator y confirma con /auth/mfa/enable/confirm.',
            'otpauth_uri' => $this->totp->provisioningUri($user->email, $secret),
            'expires_hint' => 'El secreto pendiente se reemplaza si vuelves a iniciar la activación.',
        ]);
    }

    public function confirm(MfaEnableConfirmRequest $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->mfa_pending_secret) {
            abort(422, 'No existe activación MFA pendiente.');
        }

        $secret = Crypt::decryptString($user->mfa_pending_secret);
        if (! $this->totp->verify($secret, $request->string('code')->toString())) {
            $this->audit->log($request, 'MFA_ENABLE_FAILED', $user);
            abort(401);
        }

        $user->forceFill([
            'mfa_enabled' => true,
            'mfa_secret' => Crypt::encryptString($secret),
            'mfa_pending_secret' => null,
        ])->save();

        $this->audit->log($request, 'MFA_ENABLED', $user);

        return response()->json(['message' => 'MFA activado correctamente.']);
    }
}
