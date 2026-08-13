<?php

declare(strict_types=1);

return [
    /**
     * Audit Log Configuration
     */

    // Server secret for HMAC signing (change in production!)
    'server_secret' => env('AUDIT_SERVER_SECRET', 'change-me-in-production'),

    // Retention policy settings
    'retention' => [
        // How many years to retain audit logs (default 2)
        'default_retention_years' => env('AUDIT_RETENTION_YEARS', 2),

        // Archive logs older than this many days to S3 Glacier
        'archive_after_days' => env('AUDIT_ARCHIVE_DAYS', 90),

        // Enable S3 Glacier archival
        'archive_to_s3_glacier' => env('AUDIT_ARCHIVE_TO_S3', false),

        // Automatically purge expired logs
        'purge_expired' => env('AUDIT_PURGE_EXPIRED', true),

        // Purge job interval in days
        'purge_interval_days' => 7,
    ],

    // Tamper detection settings
    'tamper_detection' => [
        // Sign all new audit logs
        'sign_logs' => env('AUDIT_SIGN_LOGS', true),

        // Check for tampered logs on read
        'verify_on_read' => env('AUDIT_VERIFY_ON_READ', true),

        // Lock logs immutable after this many days
        'immutable_after_days' => 90,

        // Track who accesses audit logs
        'track_access' => env('AUDIT_TRACK_ACCESS', true),
    ],

    // Approval override settings
    'approval_overrides' => [
        // Require reason for all overrides
        'require_reason' => true,

        // Alert CFO on finance overrides
        'alert_on_finance_override' => true,

        // Finance-related approval types
        'finance_types' => ['invoice', 'expense', 'purchase_order', 'payment', 'credit_memo'],
    ],

    // Which modules should be logged
    'logged_modules' => [
        'CRM',
        'Inventory',
        'Manufacturing',
        'Accounting',
        'HR',
        'Projects',
        'Helpdesk',
    ],

    // Actions to log (can be empty to log all)
    'logged_actions' => [
        'create',
        'update',
        'delete',
        'restore',
        'export',
        'approval_override',
    ],

    // Excluded actions (always ignored)
    'excluded_actions' => [
        'view',
        'list',
        'read',
    ],
];
