<?php

/**
 * Banking Integration Configuration
 *
 * Webhook secrets and API credentials for open banking providers
 */

return [
    // Plaid webhooks
    'plaid_webhook_secret' => env('PLAID_WEBHOOK_SECRET'),
    'plaid_client_id' => env('PLAID_CLIENT_ID'),
    'plaid_secret' => env('PLAID_SECRET'),
    'plaid_env' => env('PLAID_ENV', 'sandbox'),

    // Nordigen (GoCardless) webhooks
    'nordigen_webhook_secret' => env('NORDIGEN_WEBHOOK_SECRET'),
    'nordigen_secret_id' => env('NORDIGEN_SECRET_ID'),
    'nordigen_secret_key' => env('NORDIGEN_SECRET_KEY'),

    // Bridge webhooks
    'bridge_webhook_secret' => env('BRIDGE_WEBHOOK_SECRET'),
    'bridge_client_id' => env('BRIDGE_CLIENT_ID'),
    'bridge_client_secret' => env('BRIDGE_CLIENT_SECRET'),

    // TrueLayer webhooks
    'truelayer_webhook_secret' => env('TRUELAYER_WEBHOOK_SECRET'),
    'truelayer_client_id' => env('TRUELAYER_CLIENT_ID'),
    'truelayer_secret' => env('TRUELAYER_SECRET'),

    // Webhook retry configuration
    'webhook_retry_max_attempts' => env('WEBHOOK_RETRY_MAX_ATTEMPTS', 3),
    'webhook_retry_delay_seconds' => env('WEBHOOK_RETRY_DELAY_SECONDS', 300),
];
