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

];
