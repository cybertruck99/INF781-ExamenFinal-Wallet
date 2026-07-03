<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\FormatsResponses;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\LogoutRequest;
use App\Http\Requests\Auth\MfaVerifyRequest;
use App\Http\Requests\Auth\RefreshRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\MfaTicket;
use App\Models\RefreshToken;
use App\Models\User;
use App\Models\Wallet;
use App\Services\AuditLogger;
use App\Services\TokenService;
use App\Services\TotpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    use FormatsResponses;

    public function __construct(
        private readonly TokenService $tokens,
        private readonly TotpService $totp,
        private readonly AuditLogger $audit,
    ) {
    }

    public function captchaSiteKey(): JsonResponse
    {
        $siteKey = config('services.recaptcha.site_key');
        if (blank($siteKey)) {
            abort(503, 'reCAPTCHA no esta configurado.');
        }

        return response()->json(['site_key' => $siteKey]);
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = DB::transaction(function () use ($request) {
            $user = User::query()->create([
                'full_name' => $request->string('full_name')->trim()->toString(),
                'ci' => $request->string('ci')->trim()->toString(),
                'email' => strtolower($request->string('email')->trim()->toString()),
                'phone' => $request->string('phone')->trim()->toString(),
                'password' => Hash::make($request->string('password')->toString(), ['rounds' => config('securewallet.bcrypt_rounds', 12)]),
                'role' => 'USER',
            ]);

            Wallet::query()->create([
                'user_id' => $user->id,
                'balance_cents' => 0,
            ]);

            return $user->fresh('wallet');
        });

        $this->audit->log($request, 'REGISTER_SUCCESS', $user, ['email' => $user->email]);

        return response()->json([
            'message' => 'Usuario registrado correctamente.',
            'user' => $this->publicUser($user),
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $email = strtolower($request->string('email')->trim()->toString());
        /** @var User|null $user */
        $user = User::query()->where('email', $email)->first();

        if ($user && $user->isTemporarilyBlocked()) {
            $this->audit->log($request, 'LOGIN_BLOCKED', $user, ['email' => $email]);
            abort(423);
        }

        if (! $user || ! Hash::check($request->string('password')->toString(), $user->password)) {
            if ($user) {
                $attempts = (int) $user->failed_login_attempts + 1;
                $updates = ['failed_login_attempts' => $attempts];
                if ($attempts >= 5) {
                    $updates['blocked_until'] = now()->addMinutes(15);
                }
                $user->forceFill($updates)->save();
            }

            $this->audit->log($request, 'LOGIN_FAILED', $user, ['email' => $email]);
            abort(401);
        }

        $user->forceFill(['failed_login_attempts' => 0, 'blocked_until' => null])->save();

        if ($user->mfa_enabled) {
            $plainTicket = $this->tokens->plainToken();
            MfaTicket::query()->create([
                'user_id' => $user->id,
                'ticket_hash' => $this->tokens->hash($plainTicket),
                'expires_at' => now()->addMinutes(config('securewallet.mfa_ticket_ttl_minutes', 5)),
                'ip' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
            ]);

            $this->audit->log($request, 'LOGIN_MFA_REQUIRED', $user);

            return response()->json([
                'mfa_required' => true,
                'ticket' => $plainTicket,
                'expires_in' => config('securewallet.mfa_ticket_ttl_minutes', 5) * 60,
            ]);
        }

        $this->audit->log($request, 'LOGIN_SUCCESS', $user);

        return response()->json([
            'mfa_required' => false,
            'user' => $this->publicUser($user),
            'tokens' => $this->tokens->issuePair($user, $request),
        ]);
    }

    public function verifyMfa(MfaVerifyRequest $request): JsonResponse
    {
        /** @var MfaTicket|null $ticket */
        $ticket = MfaTicket::query()
            ->with('user')
            ->where('ticket_hash', $this->tokens->hash($request->string('ticket')->toString()))
            ->first();

        if (! $ticket || $ticket->used_at || $ticket->expires_at->isPast() || ! $ticket->user) {
            abort(401);
        }

        $secret = Crypt::decryptString($ticket->user->mfa_secret);
        if (! $this->totp->verify($secret, $request->string('code')->toString())) {
            $this->audit->log($request, 'MFA_LOGIN_FAILED', $ticket->user);
            abort(401);
        }

        $ticket->forceFill(['used_at' => now()])->save();
        $this->audit->log($request, 'LOGIN_SUCCESS', $ticket->user, ['mfa' => true]);

        return response()->json([
            'user' => $this->publicUser($ticket->user),
            'tokens' => $this->tokens->issuePair($ticket->user, $request),
        ]);
    }

    public function refresh(RefreshRequest $request): JsonResponse
    {
        $tokens = $this->tokens->refresh($request->string('refresh_token')->toString(), $request);
        $this->audit->log($request, 'REFRESH_ROTATED');

        return response()->json(['tokens' => $tokens]);
    }

    public function logout(LogoutRequest $request): JsonResponse
    {
        $access = $request->attributes->get('access_token_model');
        if ($access) {
            $this->tokens->revokeAccessToken($access);
        }

        $refresh = $request->string('refresh_token')->toString();
        if ($refresh !== '') {
            $refreshToken = RefreshToken::query()
                ->with('family')
                ->where('token_hash', $this->tokens->hash($refresh))
                ->first();
            if ($refreshToken?->family) {
                $this->tokens->revokeFamily($refreshToken->family);
            }
        } elseif ($access?->family) {
            $this->tokens->revokeFamily($access->family);
        }

        $this->audit->log($request, 'LOGOUT', $request->user());

        return response()->json(['message' => 'Sesión cerrada correctamente.']);
    }
}
