<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Cross-Origin Resource Sharing (CORS) Configuration
|--------------------------------------------------------------------------
|
| Origins are driven by the CORS_ALLOWED_ORIGINS env var (comma-separated).
| Because credentials are allowed, the wildcard "*" is intentionally NOT a
| valid origin here — the browser rejects "*" together with credentials. Set
| explicit origins per environment, e.g.:
|
|   CORS_ALLOWED_ORIGINS=https://app.widehalo.com,https://admin.widehalo.com
|
*/

$allowedOrigins = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))
)));

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'broadcasting/auth'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => $allowedOrigins,

    // Allow subdomain patterns via CORS_ALLOWED_ORIGINS_PATTERNS if needed.
    'allowed_origins_patterns' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ALLOWED_ORIGINS_PATTERNS', ''))
    ))),

    'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With', 'X-XSRF-TOKEN', 'Accept'],

    'exposed_headers' => [],

    'max_age' => 86400,

    'supports_credentials' => true,
];
