<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class RateLimitRequests
{
    public function handle(Request $request, Closure $next, string $name, int $maxAttempts = 60, int $decayMinutes = 1): Response
    {
        $actor = $request->user()?->uuid ?: $request->ip();
        $testRun = (string) $request->header('X-Postman-Test-Run', '');
        if (app()->environment(['local', 'testing']) && $testRun !== '') {
            $actor .= '|test-run:'.$testRun;
        }

        if ($name === 'login') {
            $email = strtolower(trim((string) $request->input('email', '')));
            if ($email !== '') {
                $actor .= '|'.$email;
            }
        }

        $key = sha1($name.'|'.$actor.'|'.$request->path());

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($key);
            return response()->json([
                'message' => 'Demasiadas solicitudes.',
                'retry_after' => $seconds,
            ], 429)->header('Retry-After', (string) $seconds);
        }

        RateLimiter::hit($key, $decayMinutes * 60);

        /** @var Response $response */
        $response = $next($request);
        $response->headers->set('X-RateLimit-Limit', (string) $maxAttempts);
        $response->headers->set('X-RateLimit-Remaining', (string) RateLimiter::remaining($key, $maxAttempts));

        return $response;
    }
}
