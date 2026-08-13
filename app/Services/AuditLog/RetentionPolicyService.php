<?php

declare(strict_types=1);

namespace App\Services\AuditLog;

use App\Models\Admin\AuditLog;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class RetentionPolicyService
{
    /**
     * Get retention policy configuration
     */
    public function getRetentionPolicy(): array
    {
        return config('audit.retention', [
            'default_retention_years' => 2,
            'archive_after_days' => 90,
            'archive_to_s3_glacier' => false,
            'purge_expired' => true,
            'purge_interval_days' => 7,
        ]);
    }

    /**
     * Set expiration date for a log entry
     */
    public function setExpiration(AuditLog $log, ?Carbon $expiresAt = null): void
    {
        if ($expiresAt === null) {
            $policy = $this->getRetentionPolicy();
            $expiresAt = now()->addYears($policy['default_retention_years']);
        }

        $log->update(['data_expires_at' => $expiresAt]);
    }

    /**
     * Archive logs older than specified days to S3 Glacier
     */
    public function archiveOldLogs(int $daysOld = 90): int
    {
        $logs = AuditLog::where('created_at', '<', now()->subDays($daysOld))
            ->where('archived_at', null)
            ->get();

        $archived = 0;

        foreach ($logs as $log) {
            if ($this->archiveLogToS3($log)) {
                $log->update(['archived_at' => now()]);
                $archived++;
            }
        }

        return $archived;
    }

    /**
     * Purge expired logs (after retention period)
     */
    public function purgeExpiredLogs(): int
    {
        $expired = AuditLog::where('data_expires_at', '<', now())
            ->delete();

        return $expired;
    }

    /**
     * Get logs approaching expiration
     */
    public function getExpiringLogs(int $daysUntilExpiry = 30): array
    {
        $threshold = now()->addDays($daysUntilExpiry);

        $logs = AuditLog::where('data_expires_at', '<=', $threshold)
            ->where('data_expires_at', '>', now())
            ->orderBy('data_expires_at')
            ->limit(100)
            ->get();

        return $logs->map(fn ($log) => [
            'id' => $log->id,
            'action' => $log->action,
            'user_id' => $log->user_id,
            'resource_type' => $log->resource_type,
            'created_at' => $log->created_at,
            'expires_at' => $log->data_expires_at,
            'days_until_expiry' => now()->diffInDays($log->data_expires_at),
        ])->toArray();
    }

    /**
     * Get retention summary statistics
     */
    public function getRetentionSummary(): array
    {
        $total = AuditLog::count();
        $archived = AuditLog::where('archived_at', '!=', null)->count();
        $expiring = AuditLog::where('data_expires_at', '<=', now()->addDays(30))
            ->where('data_expires_at', '>', now())
            ->count();
        $expired = AuditLog::where('data_expires_at', '<', now())->count();

        $oldestLog = AuditLog::orderBy('created_at')->first();
        $newestLog = AuditLog::orderByDesc('created_at')->first();

        return [
            'total_logs' => $total,
            'archived_logs' => $archived,
            'expiring_soon' => $expiring,
            'expired_logs' => $expired,
            'oldest_log_date' => $oldestLog?->created_at,
            'newest_log_date' => $newestLog?->created_at,
            'retention_policy' => $this->getRetentionPolicy(),
            'compliance' => [
                'gdpr_compliant' => $expired <= 100, // Should have purged old ones
                'archive_rate' => $total > 0 ? round(($archived / $total) * 100, 2) : 0,
            ],
        ];
    }

    /**
     * Export logs before expiration (for compliance)
     */
    public function exportBeforePurge(int $daysOld = 90): ?string
    {
        $logs = AuditLog::where('created_at', '<', now()->subDays($daysOld))
            ->where('archived_at', null)
            ->orderBy('created_at')
            ->get();

        if ($logs->isEmpty()) {
            return null;
        }

        $csv = "ID,User ID,Action,Resource Type,Resource ID,IP Address,Created At,Expires At\n";

        foreach ($logs as $log) {
            $csv .= implode(',', [
                $log->id,
                $log->user_id,
                '"' . $log->action . '"',
                '"' . ($log->resource_type ?? '') . '"',
                $log->resource_id,
                $log->ip_address,
                $log->created_at,
                $log->data_expires_at,
            ]) . "\n";
        }

        $filename = "audit_logs_export_" . now()->format('Ymd_His') . ".csv";
        Storage::disk('local')->put("exports/$filename", $csv);

        return $filename;
    }

    /**
     * Calculate storage savings from archiving
     */
    public function calculateArchivingSavings(): array
    {
        // Assuming ~1KB per log entry
        $total = AuditLog::count();
        $archived = AuditLog::where('archived_at', '!=', null)->count();

        $totalSize = round(($total * 1) / 1024 / 1024, 2); // MB
        $archivedSize = round(($archived * 1) / 1024 / 1024, 2); // MB
        $savedSize = $archivedSize * 0.9; // S3 Glacier saves ~90%

        return [
            'total_logs_stored_locally' => $total - $archived,
            'total_logs_archived' => $archived,
            'estimated_local_storage_mb' => $totalSize - $archivedSize,
            'estimated_archived_storage_mb' => $archivedSize,
            'estimated_storage_saved_mb' => $savedSize,
            'monthly_savings_dollars' => round($savedSize * 0.004, 2), // $0.004/GB/month S3 Glacier
        ];
    }

    /**
     * Schedule purge command (to be called from Laravel scheduler)
     */
    public function schedulePurge(): void
    {
        // Check if purge is due
        $lastPurge = cache('audit_log_last_purge');
        $policy = $this->getRetentionPolicy();

        if ($lastPurge && now()->diffInDays($lastPurge) < $policy['purge_interval_days']) {
            return;
        }

        if ($policy['purge_expired']) {
            $this->purgeExpiredLogs();
            cache(['audit_log_last_purge' => now()], now()->addDays(1));
        }
    }

    // Helper methods

    private function archiveLogToS3(AuditLog $log): bool
    {
        $policy = $this->getRetentionPolicy();

        if (!$policy['archive_to_s3_glacier']) {
            return true; // Mark as archived but don't actually archive
        }

        try {
            $filename = "audit_logs/{$log->created_at->year}/{$log->created_at->month}/{$log->id}.json";

            $content = json_encode([
                'id' => $log->id,
                'user_id' => $log->user_id,
                'action' => $log->action,
                'resource_type' => $log->resource_type,
                'resource_id' => $log->resource_id,
                'ip_address' => $log->ip_address,
                'user_agent' => $log->user_agent,
                'payload' => $log->payload,
                'created_at' => $log->created_at,
            ]);

            // Store in S3 with Glacier storage class
            Storage::disk('s3')->put($filename, $content, [
                'StorageClass' => 'GLACIER',
            ]);

            return true;
        } catch (\Exception $e) {
            \Log::error("Failed to archive audit log {$log->id} to S3: {$e->getMessage()}");
            return false;
        }
    }
}
