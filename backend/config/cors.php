<?php

$rawAllowedOriginPatterns = trim((string) env('CORS_ALLOWED_ORIGINS_PATTERNS', ''));
$allowedOriginPatterns = $rawAllowedOriginPatterns === ''
    ? []
    : array_filter(array_map('trim', explode(';', $rawAllowedOriginPatterns)));
$allowedOriginPatterns = array_map(static function (string $pattern): string {
    if ($pattern === '') {
        return $pattern;
    }

    return in_array($pattern[0], ['/', '#', '~', '%'], true)
        ? $pattern
        : '#'.str_replace('#', '\\#', $pattern).'#';
}, $allowedOriginPatterns);

return [
    'paths' => ['api/*'],
    'allowed_methods' => ['GET', 'POST', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_origins' => array_filter(array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS', env('FRONTEND_URL', 'http://localhost:5173'))))),
    'allowed_origins_patterns' => $allowedOriginPatterns,
    'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With', 'Idempotency-Key', 'Accept'],
    'exposed_headers' => ['X-RateLimit-Limit', 'X-RateLimit-Remaining'],
    'max_age' => 3600,
    'supports_credentials' => false,
];
