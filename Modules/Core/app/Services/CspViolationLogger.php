<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Core\Models\CspViolation;

/**
 * CSP Violation Logger Service
 *
 * Logs and analyzes Content Security Policy violations for security monitoring.
 * Provides methods to track violations, analyze patterns, and generate reports.
 *
 * Features:
 * - Log violations from browser reports
 * - Track violation patterns and trends
 * - Alert on high-severity or repeated violations
 * - Generate violation statistics and reports
 * - Multi-tenant isolation
 */
class CspViolationLogger
{
    /**
     * Log a CSP violation from browser report.
     *
     * Stores violation in database and logs for alerting.
     *
     * @param array $report CSP violation report from browser
     * @param int|string|null $userId Authenticated user ID (users.id is an integer PK)
     * @param string|null $tenantId Tenant ID for multi-tenant
     * @param string|null $module Module where violation occurred
     * @return CspViolation The created violation record
     */
    public function logViolation(
        array $report,
        int|string|null $userId = null,
        ?string $tenantId = null,
        ?string $module = null
    ): CspViolation {
        // Determine severity based on violation type
        $severity = $this->calculateSeverity($report);

        // Create violation record
        $violation = CspViolation::create([
            // Cast to string: Str::uuid() returns a UuidInterface object, and leaving it
            // as-is means $violation->id afterward isn't a plain string either.
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'document_uri' => $report['document-uri'] ?? '',
            'violated_directive' => $report['violated-directive'] ?? 'unknown',
            'effective_directive' => $report['effective-directive'] ?? null,
            'original_policy' => $report['original-policy'] ?? null,
            'disposition' => $report['disposition'] ?? 'enforce',
            'blocked_uri' => $report['blocked-uri'] ?? null,
            'source_file' => $report['source-file'] ?? null,
            'line_number' => $report['line-number'] ?? null,
            'column_number' => $report['column-number'] ?? null,
            'status_code' => $report['status-code'] ?? null,
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'module' => $module,
            'violation_data' => $report,
            'severity' => $severity,
            'ip_address' => request()->ip(),
            'user_agent' => request()->header('User-Agent'),
            'is_internal_request' => $this->isInternalRequest(),
        ]);

        // Log the violation
        Log::warning("CSP Violation: {$violation->getDescription()}", [
            'violation_id' => $violation->id,
            'severity' => $severity,
            'user_id' => $userId,
            'ip' => request()->ip(),
        ]);

        // Check if alert should be raised
        if ($severity === 'critical' || $this->isRepeatedViolation($violation)) {
            $this->raiseAlert($violation);
        }

        return $violation;
    }

    /**
     * Get violation statistics for a date range.
     *
     * Returns aggregated statistics about violations.
     *
     * @param \DateTime|string|null $from Start date (default 7 days ago)
     * @param \DateTime|string|null $to End date (default now)
     * @param string|null $tenantId Optional tenant filter
     * @return array Statistics array
     */
    public function getViolationStats($from = null, $to = null, ?string $tenantId = null): array
    {
        $from = $from ?? now()->subDays(7);
        $to = $to ?? now();

        // Each aggregation below needs its own query builder — Eloquent builders are
        // mutable, so reusing one instance across select()/groupBy()/orderBy() calls
        // accumulates all of them onto every subsequent call (e.g. the final count()
        // below would run the previous orderByRaw('count DESC') from byModule, which
        // fails since a plain count() query has no `count` alias to order by).
        $baseQuery = function () use ($from, $to, $tenantId) {
            $query = CspViolation::whereBetween('created_at', [$from, $to]);

            if ($tenantId) {
                $query->where('tenant_id', $tenantId);
            }

            return $query;
        };

        // Total violations
        $totalViolations = $baseQuery()->count();

        // By severity
        $bySeverity = $baseQuery()->select('severity')
            ->selectRaw('count(*) as count')
            ->groupBy('severity')
            ->pluck('count', 'severity')
            ->toArray();

        // By directive
        $byDirective = $baseQuery()->select('violated_directive')
            ->selectRaw('count(*) as count')
            ->groupBy('violated_directive')
            ->orderByRaw('count DESC')
            ->take(10)
            ->pluck('count', 'violated_directive')
            ->toArray();

        // By module
        $byModule = $baseQuery()->select('module')
            ->selectRaw('count(*) as count')
            ->whereNotNull('module')
            ->groupBy('module')
            ->orderByRaw('count DESC')
            ->take(10)
            ->pluck('count', 'module')
            ->toArray();

        // Unresolved violations
        $unresolved = $baseQuery()->whereNull('resolved_at')->count();

        return [
            'total' => $totalViolations,
            'unresolved' => $unresolved,
            'by_severity' => $bySeverity,
            'by_directive' => $byDirective,
            'by_module' => $byModule,
            'period' => [
                'from' => $from,
                'to' => $to,
            ],
        ];
    }

    /**
     * Get top violators (IPs/users causing most violations).
     *
     * Useful for identifying potential attackers or configuration issues.
     *
     * @param int $limit Number of top violators to return (default 10)
     * @param \DateTime|string|null $since Limit to violations since date
     * @return Collection Top violators
     */
    public function getTopViolators(int $limit = 10, $since = null): Collection
    {
        $query = CspViolation::query();

        if ($since) {
            $query->where('created_at', '>=', $since);
        }

        // Group by IP address and count
        $violators = $query->select('ip_address', 'user_id')
            ->selectRaw('count(*) as violation_count')
            ->selectRaw('max(created_at) as last_violation')
            ->whereNotNull('ip_address')
            ->groupBy('ip_address', 'user_id')
            ->orderByRaw('violation_count DESC')
            ->take($limit)
            ->get();

        return $violators;
    }

    /**
     * Get violation trends over time.
     *
     * Returns violation counts grouped by day for trend analysis.
     *
     * @param int $days Number of days to analyze (default 30)
     * @param string|null $tenantId Optional tenant filter
     * @return array Trend data
     */
    public function getViolationTrends(int $days = 30, ?string $tenantId = null): array
    {
        $startDate = now()->subDays($days);

        $query = CspViolation::where('created_at', '>=', $startDate);

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $trends = $query->select(
            DB::raw('DATE(created_at) as date'),
            'severity'
        )
            ->selectRaw('count(*) as count')
            ->groupBy(DB::raw('DATE(created_at)'), 'severity')
            ->orderBy('date')
            ->get()
            ->groupBy('date')
            ->map(function ($group) {
                return $group->pluck('count', 'severity')->toArray();
            })
            ->toArray();

        return $trends;
    }

    /**
     * Get violations by blocked resource.
     *
     * Useful for identifying which external resources are being blocked.
     *
     * @param int $limit Number of results (default 10)
     * @return Collection Blocked resources
     */
    public function getBlockedResources(int $limit = 10): Collection
    {
        return CspViolation::select('blocked_uri')
            ->selectRaw('count(*) as count')
            ->whereNotNull('blocked_uri')
            ->groupBy('blocked_uri')
            ->orderByRaw('count DESC')
            ->take($limit)
            ->get();
    }

    /**
     * Get violations with pattern analysis.
     *
     * Identifies patterns in violations that may indicate attacks or misconfigurations.
     *
     * @param string|null $tenantId Optional tenant filter
     * @return array Pattern analysis
     */
    public function analyzePatterns(?string $tenantId = null): array
    {
        // Same reasoning as getViolationStats(): each aggregation needs its own
        // fresh builder instance, not a shared one accumulating clauses.
        $baseQuery = function () use ($tenantId) {
            $query = CspViolation::query();

            if ($tenantId) {
                $query->where('tenant_id', $tenantId);
            }

            return $query;
        };

        // Find repeated directives
        $repeatedDirectives = $baseQuery()->select('violated_directive')
            ->selectRaw('count(*) as count')
            ->groupBy('violated_directive')
            ->having('count', '>', 10)
            ->pluck('count', 'violated_directive')
            ->toArray();

        // Find IPs with multiple violations
        $suspiciousIps = $baseQuery()->select('ip_address')
            ->selectRaw('count(*) as count')
            ->groupBy('ip_address')
            ->having('count', '>', 20)
            ->pluck('count', 'ip_address')
            ->toArray();

        // Find high-severity patterns
        $highSeverityDirectives = $baseQuery()->where('severity', 'high')
            ->orWhere('severity', 'critical')
            ->select('violated_directive')
            ->selectRaw('count(*) as count')
            ->groupBy('violated_directive')
            ->pluck('count', 'violated_directive')
            ->toArray();

        return [
            'repeated_directives' => $repeatedDirectives,
            'suspicious_ips' => $suspiciousIps,
            'high_severity_directives' => $highSeverityDirectives,
        ];
    }

    /**
     * Resolve a violation (mark as handled).
     *
     * @param string $violationId Violation ID
     * @return bool True if resolved
     */
    public function resolveViolation(string $violationId): bool
    {
        $violation = CspViolation::find($violationId);

        if (!$violation) {
            return false;
        }

        return $violation->resolve();
    }

    /**
     * Get recent critical violations.
     *
     * Returns critical violations from the last hour.
     *
     * @param int $limit Number to return
     * @return Collection Critical violations
     */
    public function getRecentCritical(int $limit = 10): Collection
    {
        return CspViolation::where('severity', 'critical')
            ->where('created_at', '>=', now()->subHour())
            ->orderBy('created_at', 'desc')
            ->take($limit)
            ->get();
    }

    /**
     * Calculate severity level for a violation.
     *
     * Determines severity based on violation type and context.
     *
     * @param array $report Violation report
     * @return string Severity level (low|medium|high|critical)
     */
    private function calculateSeverity(array $report): string
    {
        $directive = $report['violated-directive'] ?? '';
        $blockedUri = $report['blocked-uri'] ?? '';

        // Script-src violations are critical
        if (strpos($directive, 'script-src') !== false) {
            return 'critical';
        }

        // Style-src violations are high priority
        if (strpos($directive, 'style-src') !== false) {
            return 'high';
        }

        // Frame violations are medium
        if (strpos($directive, 'frame') !== false) {
            return 'medium';
        }

        // Default to medium
        return 'medium';
    }

    /**
     * Check if a violation is repeated (same source, same violation).
     *
     * @param CspViolation $violation Violation to check
     * @return bool True if repeated
     */
    private function isRepeatedViolation(CspViolation $violation): bool
    {
        // Check if same violation from same IP in last 5 minutes
        $count = CspViolation::where('ip_address', $violation->ip_address)
            ->where('violated_directive', $violation->violated_directive)
            ->where('created_at', '>=', now()->subMinutes(5))
            ->count();

        return $count > 2;
    }

    /**
     * Check if request is internal (same origin).
     *
     * @return bool True if internal request
     */
    private function isInternalRequest(): bool
    {
        $referer = request()->header('Referer');

        if (!$referer) {
            return false;
        }

        $refererHost = parse_url($referer, PHP_URL_HOST);
        $requestHost = request()->getHost();

        return $refererHost === $requestHost;
    }

    /**
     * Raise an alert for a critical violation.
     *
     * In production, this would send notifications to security team.
     *
     * @param CspViolation $violation Violation to alert about
     * @return void
     */
    private function raiseAlert(CspViolation $violation): void
    {
        // Log critical violation
        Log::alert("CSP CRITICAL VIOLATION", [
            'violation_id' => $violation->id,
            'directive' => $violation->violated_directive,
            'blocked_uri' => $violation->blocked_uri,
            'ip' => $violation->ip_address,
            'user_id' => $violation->user_id,
        ]);

        // In production, would send notification to security team
        // dispatch(new SendSecurityAlert($violation));
    }
}
