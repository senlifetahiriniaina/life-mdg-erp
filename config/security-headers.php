<?php

// Modules\Core\Services\SecurityHeadersService already defaults every one of
// these keys inline (config('security-headers.x', $default)), so the service
// works with no config file present -- this file just makes the values
// explicit/tunable instead of buried in the service class, matching the
// documented OWASP baseline (docs/09-RBAC-SECURITE/STANDARDS-SECURITE-MADAGASCAR.md).
return [

    'x_frame_options' => env('SECURITY_HEADERS_X_FRAME_OPTIONS', 'SAMEORIGIN'),

    'referrer_policy' => env('SECURITY_HEADERS_REFERRER_POLICY', 'strict-origin-when-cross-origin'),

    'cross_origin_opener_policy' => env('SECURITY_HEADERS_COOP', 'same-origin-allow-popups'),

    'cross_origin_resource_policy' => env('SECURITY_HEADERS_CORP', 'same-origin'),

    'hsts' => [
        'max_age' => (int) env('SECURITY_HEADERS_HSTS_MAX_AGE', 31536000), // 1 year
        'include_subdomains' => (bool) env('SECURITY_HEADERS_HSTS_INCLUDE_SUBDOMAINS', true),
        'preload' => (bool) env('SECURITY_HEADERS_HSTS_PRELOAD', true),
    ],

    'expect_ct' => [
        'enabled' => (bool) env('SECURITY_HEADERS_EXPECT_CT_ENABLED', true),
        'max_age' => (int) env('SECURITY_HEADERS_EXPECT_CT_MAX_AGE', 86400),
        'enforce' => (bool) env('SECURITY_HEADERS_EXPECT_CT_ENFORCE', false),
    ],

];
