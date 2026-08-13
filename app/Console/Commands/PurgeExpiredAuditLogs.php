<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\AuditLog\RetentionPolicyService;
use Illuminate\Console\Command;

class PurgeExpiredAuditLogs extends Command
{
    protected $signature = 'audit:purge-expired-logs';

    protected $description = 'Purge audit logs that have exceeded retention period';

    public function handle(): int
    {
        $this->info('Starting audit log purge process...');

        $service = new RetentionPolicyService();
        $policy = $service->getRetentionPolicy();

        if (!$policy['purge_expired']) {
            $this->warn('Purge is disabled in configuration');
            return 0;
        }

        try {
            $purged = $service->purgeExpiredLogs();
            $this->info("Successfully purged $purged expired audit logs");

            // Also archive old logs
            $archived = $service->archiveOldLogs($policy['archive_after_days']);
            $this->info("Successfully archived $archived logs to S3");

            return 0;
        } catch (\Exception $e) {
            $this->error("Error purging logs: {$e->getMessage()}");
            return 1;
        }
    }
}
