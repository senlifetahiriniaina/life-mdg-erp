<?php

// App\Http\Middleware\SecurityHeaders (the real, live, globally-registered
// security-headers middleware -- see bootstrap/app.php) reads two of these keys
// directly: cross_origin_opener_policy and cross_origin_resource_policy. The
// other keys (x_frame_options, referrer_policy, hsts.*, expect_ct.*) are
// established config surface documenting the intended OWASP baseline (see
// docs/09-RBAC-SECURITE/STANDARDS-SECURITE-MADAGASCAR.md) that the middleware
// does not yet read (it hardcodes SAMEORIGIN / strict-origin-when-cross-origin /
// a fixed HSTS value instead). Modules\Core\Services\SecurityHeadersService used
// to read all of these keys, but that class was a duplicate CSP/header engine
// that was never wired into any middleware, controller, or route -- it has been
// deleted as dead code, along with its own config/csp.php.
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
