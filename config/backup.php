<?php

/**
 * Database Backup Configuration
 *
 * Automated backup strategy for disaster recovery
 */

return [
    // Backup schedule (cron expression)
    'schedule' => env('BACKUP_SCHEDULE', '0 2 * * *'), // 02:00 UTC daily

    // Storage destination
    'disk' => env('BACKUP_DISK', 's3'), // 'local' or 's3'

    // Retention policy
    'retention_days' => env('BACKUP_RETENTION_DAYS', 30),

    // S3 configuration
    's3' => [
        'bucket' => env('BACKUP_S3_BUCKET', env('AWS_BUCKET')),
        'prefix' => env('BACKUP_S3_PREFIX', 'backups/daily'),
        'encryption' => env('BACKUP_S3_ENCRYPTION', 'AES256'),
        'storage_class' => env('BACKUP_S3_STORAGE_CLASS', 'STANDARD_IA'), // Cheaper for infrequent access
    ],

    // Local storage configuration
    'local' => [
        'path' => env('BACKUP_LOCAL_PATH', 'backups'),
    ],

    // Notification settings
    'notifications' => [
        'enabled' => env('BACKUP_NOTIFICATIONS_ENABLED', true),
        'channels' => explode(',', env('BACKUP_NOTIFICATION_CHANNELS', 'log')), // log, slack, email
        'slack_webhook' => env('BACKUP_SLACK_WEBHOOK'),
        'email' => env('BACKUP_EMAIL', env('MAIL_FROM_ADDRESS')),
    ],

    // Backup verification
    'verification' => [
        'enabled' => env('BACKUP_VERIFICATION_ENABLED', true),
        'schedule' => env('BACKUP_VERIFICATION_SCHEDULE', '0 4 */7 * *'), // Weekly at 04:00 UTC
    ],

    // Point-in-time recovery (binary logs)
    'pitr' => [
        'enabled' => env('BACKUP_PITR_ENABLED', false),
        'schedule' => env('BACKUP_PITR_SCHEDULE', '0 * * * *'), // Hourly
        'retention_hours' => env('BACKUP_PITR_RETENTION_HOURS', 72),
    ],

    // RTO/RPO targets
    'rto_hours' => 2,          // Recovery Time Objective
    'rpo_minutes' => 60,       // Recovery Point Objective
];
