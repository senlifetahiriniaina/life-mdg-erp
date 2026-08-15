<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Request Inspection (mini application-layer WAF)
    |--------------------------------------------------------------------------
    |
    | App\Http\Middleware\RequestInspectionMiddleware checks the request IP
    | against Modules\Security\Models\ThreatIndicator and scans text input
    | for XSS patterns via Modules\Core\Services\XssPreventionService.
    |
    | shadow_mode: when true (the default), matches are logged to the
    | 'security' log channel but the request is never blocked — this exists
    | because XssPreventionService's patterns can false-positive on
    | legitimate free-text fields (HR notes, CRM descriptions, Helpdesk
    | tickets), and this middleware has never run against real traffic
    | before. Flip to false only after reviewing storage/logs/security*.log
    | for an observation period and adding any needed excluded_fields.
    |
    */

    'request_inspection' => [
        'shadow_mode' => env('REQUEST_INSPECTION_SHADOW_MODE', true),

        // Field names never scanned for XSS patterns, anywhere in the app —
        // for known rich-text fields where HTML-like content is expected
        // and already handled by output encoding at render time, not input
        // rejection.
        'excluded_fields' => [
            'password',
            'password_confirmation',
        ],

        // Route name/URI patterns (fnmatch-style) never inspected at all —
        // e.g. endpoints that intentionally accept raw HTML/rich text.
        'excluded_routes' => [],

        // Minutes a known-threat-IP lookup is cached, so this middleware
        // doesn't add a DB query to every single request.
        'ip_cache_minutes' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Mandatory two-factor authentication
    |--------------------------------------------------------------------------
    |
    | App\Models\User::requiresTwoFactor() reads this instead of a hardcoded
    | role list, so a role can be added here without a code deploy. Default
    | matches the previous hardcoded behavior exactly — zero regression.
    |
    | The 2fa middleware itself is currently only wired on the root /v1
    | dashboard/webhooks/automation and /v1/admin route groups — adding a
    | role here does not, by itself, gate any of the 27 modules' own route
    | groups (tracked separately; rolling it out app-wide would 403 every
    | already-logged-in session of that role that hasn't enrolled 2FA yet,
    | so it needs its own deliberate, communicated rollout, not a silent
    | side effect of this config).
    |
    */

    'mandatory_2fa_roles' => ['super-admin', 'admin'],

];
