<?php

/**
 * Secrets Management Configuration
 *
 * Configuration for centralized secrets management including encryption,
 * rotation, access control, and audit logging.
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Encryption Configuration
    |--------------------------------------------------------------------------
    |
    | Controls encryption settings for secrets at rest
    */
    'encryption' => [
        'enabled' => env('SECRETS_ENCRYPTION_ENABLED', true),
        'cipher' => 'AES-256-GCM',
        'algorithm' => 'openssl',
    ],

    /*
    |--------------------------------------------------------------------------
    | Rotation Configuration
    |--------------------------------------------------------------------------
    |
    | Automatic secret rotation policies
    */
    'rotation' => [
        'auto_rotate_enabled' => env('SECRETS_AUTO_ROTATION_ENABLED', true),
        'default_interval' => env('SECRETS_DEFAULT_ROTATION_INTERVAL', 30), // days
        'notification_days_before' => env('SECRETS_NOTIFICATION_DAYS', '7,14,30'),
        'max_rotation_attempts' => 3,
        'rotation_timeout' => 300, // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Expiration Configuration
    |--------------------------------------------------------------------------
    |
    | Secret expiration and lifecycle management
    */
    'expiration' => [
        'default_ttl' => env('SECRETS_DEFAULT_TTL', 90), // days
        'enable_expiration' => env('SECRETS_ENABLE_EXPIRATION', true),
        'cleanup_interval' => 'daily', // When to clean up expired secrets
    ],

    /*
    |--------------------------------------------------------------------------
    | Access Control Configuration
    |--------------------------------------------------------------------------
    |
    | Role-based and fine-grained access control
    */
    'access_control' => [
        'require_mfa_for_access' => env('SECRETS_REQUIRE_MFA', false),
        'require_approval' => env('SECRETS_REQUIRE_APPROVAL', false),
        'rate_limit' => env('SECRETS_RATE_LIMIT_PER_MINUTE', 60),
        'rate_limit_window' => 60, // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Configuration
    |--------------------------------------------------------------------------
    |
    | Logging and audit trail for all secret operations
    */
    'audit' => [
        'enabled' => env('SECRETS_AUDIT_ENABLED', true),
        'log_all_access' => env('SECRETS_LOG_ALL_ACCESS', true),
        'log_failures' => true,
        'log_channel' => env('SECRETS_LOG_CHANNEL', 'secrets'),
        'retention_days' => env('SECRETS_AUDIT_RETENTION', 365),
    ],

    /*
    |--------------------------------------------------------------------------
    | Masking Configuration
    |--------------------------------------------------------------------------
    |
    | Masking secrets in logs and responses
    */
    'masking' => [
        'enabled' => env('SECRETS_MASKING_ENABLED', true),
        'show_chars' => 4, // Show first N characters
        'mask_char' => '*',
    ],

    /*
    |--------------------------------------------------------------------------
    | Secret Types
    |--------------------------------------------------------------------------
    |
    | Define available secret types and their configurations
    */
    'types' => [
        'api_key' => [
            'label' => 'API Key',
            'description' => 'API authentication key',
            'icon' => 'key',
        ],
        'oauth_token' => [
            'label' => 'OAuth Token',
            'description' => 'OAuth 2.0 access token',
            'icon' => 'token',
            'default_ttl' => 3600, // 1 hour
        ],
        'database_credential' => [
            'label' => 'Database Credential',
            'description' => 'Database connection credentials',
            'icon' => 'database',
        ],
        'ssh_key' => [
            'label' => 'SSH Key',
            'description' => 'SSH private key for authentication',
            'icon' => 'ssh',
        ],
        'certificate' => [
            'label' => 'Certificate',
            'description' => 'SSL/TLS certificate or key',
            'icon' => 'certificate',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | API Key Configuration
    |--------------------------------------------------------------------------
    |
    | Service account API keys with scopes
    */
    'api_keys' => [
        'default_ttl' => env('SECRETS_API_KEY_TTL', 365), // days
        'max_keys_per_user' => env('SECRETS_MAX_KEYS_PER_USER', 10),
        'scopes' => [
            'read' => 'Read secrets',
            'create' => 'Create secrets',
            'rotate' => 'Rotate secrets',
            'revoke' => 'Revoke secrets',
            'admin' => 'Administrator access',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Configuration
    |--------------------------------------------------------------------------
    |
    | Notifications for expiration, rotation, and access events
    */
    'notifications' => [
        'enabled' => true,
        'channels' => ['mail', 'database'],
        'send_to_creator' => true,
        'send_to_accessors' => true,
        'send_rotation_reminders' => true,
        'send_expiration_warnings' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    |
    | Validation rules for secret creation and management
    */
    'validation' => [
        'name' => 'required|string|max:255|regex:/^[a-zA-Z0-9_\-\.]+$/',
        'min_name_length' => 3,
        'max_name_length' => 255,
        'min_value_length' => 1,
        'max_value_length' => 65535,
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Features
    |--------------------------------------------------------------------------
    |
    | Advanced security hardening options
    */
    'security' => [
        'enable_versioning' => true,
        'keep_rotation_history' => true,
        'max_versions' => 10,
        'verify_rotation' => true,
        'enable_rollback' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage Configuration
    |--------------------------------------------------------------------------
    |
    | Where and how secrets are stored
    */
    'storage' => [
        'disk' => 'local',
        'path' => 'storage/secrets/',
        'use_database' => env('SECRETS_USE_DATABASE', true),
        'backup_enabled' => env('SECRETS_BACKUP_ENABLED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Optimization
    |--------------------------------------------------------------------------
    |
    | Caching and performance settings
    */
    'performance' => [
        'cache_enabled' => env('SECRETS_CACHE_ENABLED', true),
        'cache_ttl' => env('SECRETS_CACHE_TTL', 300), // seconds
        'cache_prefix' => 'secrets:',
    ],
];
