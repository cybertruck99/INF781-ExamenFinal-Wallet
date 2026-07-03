<?php

return [
    'access_token_ttl_minutes' => min(15, max(1, (int) env('ACCESS_TOKEN_TTL_MINUTES', 15))),
    'refresh_token_ttl_days' => (int) env('REFRESH_TOKEN_TTL_DAYS', 7),
    'mfa_ticket_ttl_minutes' => (int) env('MFA_TICKET_TTL_MINUTES', 5),
    'pending_transfer_ttl_seconds' => (int) env('PENDING_TRANSFER_TTL_SECONDS', 120),
    'bcrypt_rounds' => max(12, (int) env('BCRYPT_ROUNDS', 12)),
];
