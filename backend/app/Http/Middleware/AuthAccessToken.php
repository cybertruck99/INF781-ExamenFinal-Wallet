<?php

namespace App\Http\Middleware;

use App\Models\AccessToken;
use App\Services\TokenService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthAccessToken
{
    public function __construct(private readonly TokenService $tokens)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $plain = $request->bearerToken();
        if (! $plain) {
            abort(401);
        }

        /** @var AccessToken|null $token */
        $token = AccessToken::query()
            ->with(['user', 'family'])
            ->where('token_hash', $this->tokens->hash($plain))
            ->first();

        if (! $token || $token->revoked_at || $token->expires_at->isPast() || ! $token->user) {
            abort(401);
        }

        if ($token->family?->revoked_at || $token->user->isTemporarilyBlocked()) {
            abort(401);
        }

        $request->setUserResolver(fn () => $token->user);
        $request->attributes->set('access_token_model', $token);

        return $next($request);
    }
}
