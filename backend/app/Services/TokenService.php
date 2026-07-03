<?php

namespace App\Services;

use App\Models\AccessToken;
use App\Models\RefreshToken;
use App\Models\RefreshTokenFamily;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class TokenService
{
    public function issuePair(User $user, Request $request, ?RefreshTokenFamily $family = null): array
    {
        return DB::transaction(function () use ($user, $request, $family) {
            $family ??= RefreshTokenFamily::query()->create(['user_id' => $user->id]);

            $plainAccess = $this->plainToken();
            $plainRefresh = $this->plainToken();

            $access = AccessToken::query()->create([
                'user_id' => $user->id,
                'family_id' => $family->id,
                'token_hash' => $this->hash($plainAccess),
                'expires_at' => now()->addMinutes(config('securewallet.access_token_ttl_minutes', 15)),
                'ip' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
            ]);

            RefreshToken::query()->create([
                'family_id' => $family->id,
                'user_id' => $user->id,
                'token_hash' => $this->hash($plainRefresh),
                'expires_at' => now()->addDays(config('securewallet.refresh_token_ttl_days', 7)),
                'ip' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
            ]);

            return [
                'token_type' => 'Bearer',
                'access_token' => $plainAccess,
                'expires_in' => config('securewallet.access_token_ttl_minutes', 15) * 60,
                'refresh_token' => $plainRefresh,
                'access_token_uuid' => $access->uuid,
            ];
        });
    }

    public function refresh(string $plainRefreshToken, Request $request): array
    {
        return DB::transaction(function () use ($plainRefreshToken, $request) {
            /** @var RefreshToken|null $token */
            $token = RefreshToken::query()
                ->where('token_hash', $this->hash($plainRefreshToken))
                ->lockForUpdate()
                ->first();

            if (! $token || $token->expires_at->isPast() || $token->revoked_at) {
                throw new UnauthorizedHttpException('', 'Refresh token inválido.');
            }

            $family = RefreshTokenFamily::query()->lockForUpdate()->find($token->family_id);
            if (! $family || $family->revoked_at) {
                throw new UnauthorizedHttpException('', 'Refresh token inválido.');
            }

            if ($token->used_at !== null) {
                $this->revokeFamily($family);
                throw new UnauthorizedHttpException('', 'Reutilización de refresh token detectada.');
            }

            $token->forceFill(['used_at' => now()])->save();

            $user = User::query()->findOrFail($token->user_id);

            return $this->issuePair($user, $request, $family);
        });
    }

    public function revokeAccessToken(AccessToken $accessToken): void
    {
        $accessToken->forceFill(['revoked_at' => now()])->save();
    }

    public function revokeFamily(RefreshTokenFamily $family): void
    {
        $family->forceFill(['revoked_at' => now()])->save();
        RefreshToken::query()->where('family_id', $family->id)->update(['revoked_at' => now()]);
        AccessToken::query()->where('family_id', $family->id)->update(['revoked_at' => now()]);
    }

    public function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    public function plainToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
    }
}
