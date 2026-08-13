<?php

declare(strict_types=1);

namespace App\Services\AuditLog;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ApprovalOverrideService
{
    /**
     * Record an approval override
     */
    public function recordOverride(
        int $approverId,
        int $originalApprovalId,
        string $reason,
        string $approvalType = 'general',
        ?int $relatedResourceId = null,
        ?string $relatedResourceType = null
    ): array {
        $override = DB::table('approval_overrides')->insertGetId([
            'approver_id' => $approverId,
            'original_approval_id' => $originalApprovalId,
            'reason' => $reason,
            'approval_type' => $approvalType,
            'related_resource_id' => $relatedResourceId,
            'related_resource_type' => $relatedResourceType,
            'created_at' => now(),
        ]);

        // Log audit entry
        \DB::table('admin_audit_logs')->insert([
            'user_id' => $approverId,
            'action' => 'approval_override',
            'resource_type' => $approvalType,
            'resource_id' => $relatedResourceId,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'payload' => json_encode([
                'override_id' => $override,
                'reason' => $reason,
                'approval_type' => $approvalType,
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Alert CFO on finance-related overrides
        if (in_array($approvalType, ['invoice', 'expense', 'purchase_order'])) {
            $this->alertCFO($approverId, $approvalType, $reason);
        }

        return [
            'id' => $override,
            'approver_id' => $approverId,
            'approval_type' => $approvalType,
            'recorded_at' => now(),
        ];
    }

    /**
     * Get all overrides for a specific approval
     */
    public function getOverridesForApproval(int $approvalId): array
    {
        return DB::table('approval_overrides')
            ->where('original_approval_id', $approvalId)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($override) => [
                'id' => $override->id,
                'approver_id' => $override->approver_id,
                'approver_name' => User::find($override->approver_id)?->name ?? 'Unknown',
                'reason' => $override->reason,
                'created_at' => $override->created_at,
            ])
            ->toArray();
    }

    /**
     * Get override history for a user
     */
    public function getUserOverrideHistory(int $userId, int $daysBack = 90): array
    {
        return DB::table('approval_overrides')
            ->where('approver_id', $userId)
            ->where('created_at', '>=', now()->subDays($daysBack))
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($override) => [
                'id' => $override->id,
                'approval_type' => $override->approval_type,
                'related_resource_type' => $override->related_resource_type,
                'related_resource_id' => $override->related_resource_id,
                'reason' => $override->reason,
                'created_at' => $override->created_at,
                'days_ago' => now()->diffInDays($override->created_at),
            ])
            ->toArray();
    }

    /**
     * Get override statistics
     */
    public function getOverrideStatistics(int $daysBack = 90): array
    {
        $overrides = DB::table('approval_overrides')
            ->where('created_at', '>=', now()->subDays($daysBack))
            ->get();

        $byType = $overrides->groupBy('approval_type')
            ->map->count();

        $byApprover = $overrides->groupBy('approver_id')
            ->map->count();

        $topOverriders = DB::table('approval_overrides')
            ->where('created_at', '>=', now()->subDays($daysBack))
            ->select('approver_id')
            ->selectRaw('COUNT(*) as override_count')
            ->groupBy('approver_id')
            ->orderByDesc('override_count')
            ->limit(10)
            ->get()
            ->map(fn ($record) => [
                'user_id' => $record->approver_id,
                'user_name' => User::find($record->approver_id)?->name ?? 'Unknown',
                'override_count' => $record->override_count,
            ])
            ->toArray();

        return [
            'total_overrides' => $overrides->count(),
            'by_type' => $byType->toArray(),
            'by_approver_count' => $byApprover->count(),
            'top_overriders' => $topOverriders,
            'period_days' => $daysBack,
            'average_per_day' => round($overrides->count() / $daysBack, 2),
        ];
    }

    /**
     * Detect unusual override activity
     */
    public function detectAnomalies(): array
    {
        $anomalies = [];

        // Check for users with abnormally high override count
        $overrideStats = DB::table('approval_overrides')
            ->where('created_at', '>=', now()->subDays(30))
            ->select('approver_id')
            ->selectRaw('COUNT(*) as override_count')
            ->groupBy('approver_id')
            ->having('override_count', '>', 10)
            ->get();

        foreach ($overrideStats as $stat) {
            $anomalies[] = [
                'type' => 'high_override_count',
                'severity' => 'medium',
                'user_id' => $stat->approver_id,
                'user_name' => User::find($stat->approver_id)?->name ?? 'Unknown',
                'override_count_30_days' => $stat->override_count,
                'recommendation' => 'Review override activity and approval procedures',
            ];
        }

        // Check for overrides without adequate reason
        $emptyReasons = DB::table('approval_overrides')
            ->where('created_at', '>=', now()->subDays(30))
            ->where(function ($query) {
                $query->whereNull('reason')
                    ->orWhere('reason', '');
            })
            ->count();

        if ($emptyReasons > 0) {
            $anomalies[] = [
                'type' => 'missing_override_reasons',
                'severity' => 'high',
                'count' => $emptyReasons,
                'recommendation' => 'Require reasons for all approval overrides',
            ];
        }

        // Check for overrides on critical documents (invoices, expenses)
        $criticalOverrides = DB::table('approval_overrides')
            ->where('created_at', '>=', now()->subDays(30))
            ->whereIn('approval_type', ['invoice', 'expense', 'purchase_order', 'payment'])
            ->count();

        if ($criticalOverrides > 5) {
            $anomalies[] = [
                'type' => 'critical_overrides',
                'severity' => 'critical',
                'count' => $criticalOverrides,
                'recommendation' => 'Investigate overrides on financial documents',
            ];
        }

        return $anomalies;
    }

    /**
     * Generate override report for audit
     */
    public function generateAuditReport(int $daysBack = 90): array
    {
        $overrides = DB::table('approval_overrides')
            ->where('created_at', '>=', now()->subDays($daysBack))
            ->get();

        if ($overrides->isEmpty()) {
            return [
                'period_days' => $daysBack,
                'total_overrides' => 0,
                'status' => 'compliant',
                'message' => 'No overrides detected in period',
            ];
        }

        $stats = $this->getOverrideStatistics($daysBack);
        $anomalies = $this->detectAnomalies();

        return [
            'period_days' => $daysBack,
            'total_overrides' => $stats['total_overrides'],
            'by_type' => $stats['by_type'],
            'top_overriders' => $stats['top_overriders'],
            'anomalies_detected' => count($anomalies) > 0,
            'anomalies' => $anomalies,
            'status' => count($anomalies) > 0 ? 'requires_review' : 'compliant',
            'generated_at' => now(),
        ];
    }

    // Helper methods

    private function alertCFO(int $approverId, string $approvalType, string $reason): void
    {
        // Find CFO or finance manager
        $cfo = User::whereHas('roles', function ($query) {
            $query->where('name', 'finance-manager')
                ->orWhere('name', 'super-admin');
        })->first();

        if (!$cfo) {
            return;
        }

        $approver = User::find($approverId);

        // Create notification (implement your notification system)
        \Log::warning("APPROVAL OVERRIDE ALERT", [
            'approver' => $approver?->name,
            'approval_type' => $approvalType,
            'reason' => $reason,
            'timestamp' => now(),
        ]);

        // TODO: Send email/notification to CFO
    }
}
