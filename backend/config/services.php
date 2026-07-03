<?php

return [
    'recaptcha' => [
        'site_key' => env('RECAPTCHA_SITE_KEY'),
        'secret_key' => env('RECAPTCHA_SECRET_KEY'),
        'verify_url' => env('RECAPTCHA_VERIFY_URL', 'https://www.google.com/recaptcha/api/siteverify'),
        'ssl_verify' => filter_var(env('RECAPTCHA_SSL_VERIFY', true), FILTER_VALIDATE_BOOLEAN),
        'ca_bundle' => env('RECAPTCHA_CA_BUNDLE', storage_path('certs/cacert.pem')),
        'timeout' => (int) env('RECAPTCHA_TIMEOUT', 4),
        'connect_timeout' => (int) env('RECAPTCHA_CONNECT_TIMEOUT', 2),
        'test_token' => env('CAPTCHA_TEST_TOKEN'),
    ],
];
