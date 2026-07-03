<?php

use App\Http\Middleware\AuthAccessToken;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\RateLimitRequests;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: ''
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            ForceJsonResponse::class,
            \Illuminate\Http\Middleware\HandleCors::class,
            SecurityHeaders::class,
        ]);

        $middleware->alias([
            'auth.token' => AuthAccessToken::class,
            'admin' => EnsureAdmin::class,
            'rate.limit' => RateLimitRequests::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            if ($e instanceof ValidationException) {
                return response()->json([
                    'message' => 'Datos de entrada inválidos.',
                    'errors' => $e->errors(),
                ], 422);
            }

            if ($e instanceof ModelNotFoundException) {
                return response()->json(['message' => 'Recurso no encontrado.'], 404);
            }

            $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;
            $fallback = match ($status) {
                401 => 'No autenticado.',
                403 => 'No autorizado.',
                404 => 'Recurso no encontrado.',
                409 => 'Conflicto de operación.',
                422 => 'Datos de entrada inválidos.',
                423 => 'Cuenta bloqueada temporalmente.',
                429 => 'Demasiadas solicitudes.',
                default => 'Error interno del servidor.',
            };
            $message = ($status < 500 && $e->getMessage() !== '')
                ? $e->getMessage()
                : (config('app.debug') ? ($e->getMessage() ?: $fallback) : $fallback);

            $payload = ['message' => $message];
            if (config('app.debug') && $status >= 500) {
                $payload['exception'] = class_basename($e);
                $payload['file'] = $e->getFile();
                $payload['line'] = $e->getLine();
            }

            return response()->json($payload, $status);
        });
    })
    ->create();
