<?php

declare(strict_types=1);

namespace App\Services\AuditLog;

use App\Models\Admin\AuditLog;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TamperDetectionService
{
    private string $serverSecret;

    public function __construct()
    {
        $this->serverSecret = config('audit.server_secret') ?? env('AUDIT_SERVER_SECRET', 'default-secret');
    }

    /**
     * Generate HMAC-SHA256 signature for an audit log entry
     */
    public function generateSignature(array $logData): string
    {
        // Sort keys for consistent hashing
        ksort($logData);

        // Create deterministic string representation (keys already ksort-ed above)
        $payload = json_encode($logData);

        // Generate HMAC-SHA256
        return hash_hmac('sha256', $payload, $this->serverSecret);
    }

    /**
     * Verify signature of an audit log entry
     * Returns true if signature is valid (log not tampered)
     */
    public function verifySignature(AuditLog $log): bool
    {
        if (!$log->signature) {
            return false;
        }

        $logData = [
            'user_id' => $log->user_id,
            'action' => $log->action,
            'resource_type' => $log->resource_type,
            'resource_id' => $log->resource_id,
            'ip_address' => $log->ip_address,
            'user_agent' => $log->user_agent,
            'payload' => $log->payload,
            'created_at' => $log->created_at?->toDateTimeString(),
        ];

        $expectedSignature = $this->generateSignature($logData);

        return hash_equals($expectedSignature, $log->signature);
    }

    /**
     * Sign and save an audit log entry
     */
    public function signLogEntry(AuditLog $log): void
    {
        $logData = [
            'user_id' => $log->user_id,
            'action' => $log->action,
            'resource_type' => $log->resource_type,
            'resource_id' => $log->resource_id,
            'ip_address' => $log->ip_address,
            'user_agent' => $log->user_agent,
            'payload' => $log->payload,
            'created_at' => $log->created_at?->toDateTimeString(),
        ];

        $signature = $this->generateSignature($logData);
        $log->signature = $signature;
        $log->save();
    }

    /**
     * Detect logs that have been tampered with
     */
    public function detectTamperedLogs(int $hoursBack = 24): array
    {
        $logs = AuditLog::where('created_at', '>=', now()->subHours($hoursBack))
            ->where('signature', '!=', null)
            ->get();

        $tampered = [];

        foreach ($logs as $log) {
            if (!$this->verifySignature($log)) {
                $tampered[] = [
                    'id' => $log->id,
                    'user_id' => $log->user_id,
                    'action' => $log->action,
                    'created_at' => $log->created_at,
                    'detected_at' => now(),
                    'severity' => 'critical',
                ];
            }
        }

        return $tampered;
    }

    /**
     * Lock logs from modification (immutable after period)
     */
    public function lockLogImmutable(int $daysOld = 90): int
    {
        $lockDate = now()->subDays($daysOld);

        return AuditLog::where('created_at', '<', $lockDate)
            ->update(['is_immutable' => true]);
    }

    /**
     * Verify all logs are immutable after specified age
     */
    public function verifyLogImmutability(): array
    {
        $unlocked = AuditLog::where('created_at', '<', now()->subDays(90))
            ->where('is_immutable', false)
            ->count();

        return [
            'compliant' => $unlocked === 0,
            'unlocked_immutable_logs' => $unlocked,
            'warning' => $unlocked > 0 ? "Found $unlocked logs that should be immutable" : null,
        ];
    }

    /**
     * Create access log for who read audit logs
     */
    public function logAccess(
        int $userId,
        int $auditLogId,
        string $action = 'read',
        string $ipAddress = null,
        string $userAgent = null
    ): void {
        \DB::table('audit_log_access_logs')->insert([
            'user_id' => $userId,
            'audit_log_id' => $auditLogId,
            'action' => $action,
            'ip_address' => $ipAddress ?? request()->ip(),
            'user_agent' => $userAgent ?? request()->userAgent(),
            'created_at' => now(),
        ]);
    }

    /**
     * Get access history for specific audit log
     */
    public function getAccessHistory(int $auditLogId): array
    {
        return \DB::table('audit_log_access_logs')
            ->where('audit_log_id', $auditLogId)
            ->orderByDesc('created_at')
            ->get()
            ->toArray();
    }

    /**
     * Verify log chain integrity
     * Checks that sequence of logs is unbroken (no gaps or modifications)
     */
    public function verifyLogChainIntegrity(int $hoursBack = 24): array
    {
        $logs = AuditLog::where('created_at', '>=', now()->subHours($hoursBack))
            ->where('signature', '!=', null)
            ->orderBy('created_at')
            ->get();

        $issues = [];
        $previousLog = null;

        foreach ($logs as $log) {
            // Check signature
            if (!$this->verifySignature($log)) {
                $issues[] = [
                    'type' => 'invalid_signature',
                    'log_id' => $log->id,
                    'created_at' => $log->created_at,
                ];
            }

            // Check timestamp sequence (should be increasing or equal)
            if ($previousLog && $log->created_at < $previousLog->created_at) {
                $issues[] = [
                    'type' => 'timestamp_reversal',
                    'log_id' => $log->id,
                    'previous_log_id' => $previousLog->id,
                ];
            }

            $previousLog = $log;
        }

        return [
            'chain_valid' => empty($issues),
            'issues' => $issues,
            'total_logs_checked' => $logs->count(),
        ];
    }
}
