<?php

/**
 * API Rate Limiting Configuration
 *
 * Configures per-tenant and per-user rate limits based on subscription tier.
 * Uses Redis for distributed rate limiting across multiple servers.
 */

return [
    // Global per-user rate limit (across all tenants)
    'global_per_user_limit' => env('RATE_LIMIT_GLOBAL_USER', '500,60'), // 500 requests per minute per user

    // Tenant-based rate limits (per tenant + per user)
    'tenant_tiers' => [
        'free' => [
            'requests_per_minute' => 60,       // 60 req/min per user
            'requests_per_hour' => 3000,       // 3,000 req/hour per user
            'concurrent_requests' => 5,        // Max 5 simultaneous requests
            'description' => 'Free plan - basic rate limits',
        ],
        'starter' => [
            'requests_per_minute' => 200,      // 200 req/min per user
            'requests_per_hour' => 12000,      // 12,000 req/hour per user
            'concurrent_requests' => 20,       // Max 20 simultaneous
            'description' => 'Starter plan - moderate rate limits',
        ],
        'professional' => [
            'requests_per_minute' => 500,      // 500 req/min per user
            'requests_per_hour' => 30000,      // 30,000 req/hour per user
            'concurrent_requests' => 50,       // Max 50 simultaneous
            'description' => 'Professional plan - high rate limits',
        ],
        'enterprise' => [
            'requests_per_minute' => 2000,     // 2,000 req/min per user
            'requests_per_hour' => 120000,     // 120,000 req/hour per user
            'concurrent_requests' => 200,      // Max 200 simultaneous
            'description' => 'Enterprise plan - unlimited rate limits',
        ],
    ],

    // IP-based rate limits (for public endpoints like webhooks)
    'ip_limits' => [
        'enabled' => true,
        'requests_per_minute' => 100,         // Per IP address
        'whitelist' => env('RATE_LIMIT_WHITELIST_IPS', ''), // Comma-separated IPs
    ],

    // Webhook rate limits (special handling for Stripe, etc.)
    'webhook_limits' => [
        'stripe' => [
            'enabled' => false,                // Stripe webhooks exempt from rate limits
            'requests_per_minute' => 10000,    // If enabled, very high limit
        ],
        'banking' => [
            'enabled' => false,                // Banking webhooks exempt
            'requests_per_minute' => 5000,
        ],
    ],

    // Cache store for rate limit tracking
    'cache_store' => env('RATE_LIMIT_CACHE_STORE', 'redis'),

    // Enable rate limit headers in responses
    'headers_enabled' => true,

    // Custom rate limit keys (can be tenant_id, user_id, ip, etc.)
    'key_prefix' => 'rate_limit:',

    // Redis cluster configuration (for high-volume deployments)
    'redis_cluster' => env('REDIS_CLUSTER_ENABLED', false),
];
