<?php

// Modules\Core\Services\SecurityHeadersService::getBasePolicy()/getModuleCspPolicy() already
// default every key inline, so the service works with no config file present -- this file
// makes the base CSP policy and per-module overrides explicit/tunable instead of buried in
// the service class, matching config/security-headers.php's already-established pattern.
return [

    'default_policy' => [
        'default-src' => "'self'",
        'script-src' => "'self'",
        'style-src' => "'self'",
        'img-src' => "'self' data: blob: https:",
        'font-src' => "'self' data:",
        'connect-src' => "'self'",
        'frame-src' => "'none'",
        'object-src' => "'none'",
        'base-uri' => "'self'",
        'form-action' => "'self'",
    ],

    'module_policies' => [
        'Accounting' => [
            'connect-src' => "'self' https://api.anthropic.com",
        ],
        'Ecommerce' => [
            'connect-src' => "'self' https://api.stripe.com https://js.stripe.com",
        ],
    ],

    'role_policies' => [],

    'report_only' => (bool) env('CSP_REPORT_ONLY', false),

    'report_uri' => env('CSP_REPORT_URI', '/api/security/csp-violations'),

    'nonce_store' => env('CSP_NONCE_STORE', 'array'),

    'nonce_lifetime' => (int) env('CSP_NONCE_LIFETIME', 300),

];
