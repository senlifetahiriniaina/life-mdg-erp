<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Services\CspViolationLogger;

/**
 * CSP Violations Analysis Command
 *
 * Analyzes CSP violations and provides security insights.
 *
 * Usage:
 *   php artisan csp:analyze                          # Last 7 days
 *   php artisan csp:analyze --days=30                # Last 30 days
 *   php artisan csp:analyze --tenant=uuid            # Specific tenant
 *   php artisan csp:analyze --critical               # Critical violations only
 *   php artisan csp:analyze --show-patterns          # Include pattern analysis
 */
class CspAnalyzeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'csp:analyze
                            {--days=7 : Number of days to analyze}
                            {--tenant= : Analyze specific tenant}
                            {--critical : Show only critical violations}
                            {--show-patterns : Include pattern analysis}
                            {--export= : Export results to file (json|csv|txt)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Analyze CSP violations and provide security insights';

    /**
     * CSP Violation Logger service.
     */
    private CspViolationLogger $logger;

    /**
     * Execute the console command.
     */
    public function handle(CspViolationLogger $logger): int
    {
        $this->logger = $logger;

        try {
            $days = (int) $this->option('days');
            $tenantId = $this->option('tenant');
            $showPatterns = $this->option('show-patterns');
            $critical = $this->option('critical');
            $exportFormat = $this->option('export');

            $this->info("CSP Violation Analysis (Last {$days} days)");
            $this->line('');

            // Get violation statistics
            $from = now()->subDays($days);
            $to = now();

            if ($critical) {
                $this->info('Mode: Critical Violations Only');
            }

            // Display statistics
            $this->displayStatistics($from, $to, $tenantId);

            // Display top violators
            $this->displayTopViolators($from);

            // Display top blocked resources
            $this->displayBlockedResources();

            // Display pattern analysis
            if ($showPatterns) {
                $this->displayPatternAnalysis($tenantId);
            }

            // Export if requested
            if ($exportFormat) {
                $this->exportResults($from, $to, $tenantId, $exportFormat);
            }

            $this->info('Analysis complete.');

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Error analyzing violations: ' . $e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Display violation statistics.
     *
     * @param \DateTime $from
     * @param \DateTime $to
     * @param string|null $tenantId
     */
    private function displayStatistics(\DateTime $from, \DateTime $to, ?string $tenantId): void
    {
        $stats = $this->logger->getViolationStats($from, $to, $tenantId);

        $this->info('Summary:');
        $this->line("  Total Violations: {$stats['total']}");
        $this->line("  Unresolved: {$stats['unresolved']}");

        if (! empty($stats['by_severity'])) {
            $this->line('');
            $this->info('By Severity:');
            foreach ($stats['by_severity'] as $severity => $count) {
                $icon = match ($severity) {
                    'critical' => '🔴',
                    'high' => '🟠',
                    'medium' => '🟡',
                    'low' => '🟢',
                    default => '⚪',
                };
                $this->line("  {$icon} {$severity}: {$count}");
            }
        }

        if (! empty($stats['by_directive'])) {
            $this->line('');
            $this->info('Top Violated Directives:');
            foreach ($stats['by_directive'] as $directive => $count) {
                $this->line("  {$directive}: {$count}");
            }
        }

        if (! empty($stats['by_module'])) {
            $this->line('');
            $this->info('By Module:');
            foreach ($stats['by_module'] as $module => $count) {
                $this->line("  {$module}: {$count}");
            }
        }

        $this->line('');
    }

    /**
     * Display top violators.
     *
     * @param \DateTime $from
     */
    private function displayTopViolators(\DateTime $from): void
    {
        $violators = $this->logger->getTopViolators(10, $from);

        if ($violators->isEmpty()) {
            return;
        }

        $this->info('Top Violators (Last 24 hours):');

        $headers = ['IP Address', 'User ID', 'Violations'];
        $rows = $violators->map(function ($row) {
            return [
                $row->ip_address ?? 'unknown',
                $row->user_id ?? 'anonymous',
                $row->violation_count,
            ];
        })->toArray();

        $this->table($headers, $rows);
        $this->line('');
    }

    /**
     * Display top blocked resources.
     */
    private function displayBlockedResources(): void
    {
        $resources = $this->logger->getBlockedResources(10);

        if ($resources->isEmpty()) {
            return;
        }

        $this->info('Top Blocked Resources:');

        $headers = ['URI', 'Count'];
        $rows = $resources->map(function ($row) {
            return [
                substr($row->blocked_uri, 0, 70),
                $row->count,
            ];
        })->toArray();

        $this->table($headers, $rows);
        $this->line('');
    }

    /**
     * Display pattern analysis.
     *
     * @param string|null $tenantId
     */
    private function displayPatternAnalysis(?string $tenantId): void
    {
        $patterns = $this->logger->analyzePatterns($tenantId);

        if (empty($patterns['repeated_directives']) &&
            empty($patterns['suspicious_ips']) &&
            empty($patterns['high_severity_directives'])) {
            return;
        }

        $this->line('');
        $this->info('Pattern Analysis:');

        if (! empty($patterns['repeated_directives'])) {
            $this->warn('Repeated Directives (>10 violations):');
            foreach ($patterns['repeated_directives'] as $directive => $count) {
                $this->line("  {$directive}: {$count}");
            }
        }

        if (! empty($patterns['suspicious_ips'])) {
            $this->warn('Suspicious IPs (>20 violations):');
            foreach ($patterns['suspicious_ips'] as $ip => $count) {
                $this->line("  {$ip}: {$count}");
            }
        }

        if (! empty($patterns['high_severity_directives'])) {
            $this->warn('High Severity Directives:');
            foreach ($patterns['high_severity_directives'] as $directive => $count) {
                $this->line("  {$directive}: {$count}");
            }
        }

        $this->line('');
    }

    /**
     * Export results to file.
     *
     * @param \DateTime $from
     * @param \DateTime $to
     * @param string|null $tenantId
     * @param string $format
     */
    private function exportResults(\DateTime $from, \DateTime $to, ?string $tenantId, string $format): void
    {
        $stats = $this->logger->getViolationStats($from, $to, $tenantId);

        $filename = storage_path("logs/csp-analysis-" . now()->format('Y-m-d-H-i-s') . ".{$format}");

        match ($format) {
            'json' => file_put_contents($filename, json_encode($stats, JSON_PRETTY_PRINT)),
            'csv' => $this->exportCsv($filename, $stats),
            'txt' => file_put_contents($filename, $this->formatAsText($stats)),
            default => $this->error("Unsupported export format: {$format}"),
        };

        $this->info("Results exported to: {$filename}");
    }

    /**
     * Export results as CSV.
     *
     * @param string $filename
     * @param array $stats
     */
    private function exportCsv(string $filename, array $stats): void
    {
        $fp = fopen($filename, 'w');

        // Write summary
        fputcsv($fp, ['Total Violations', $stats['total']]);
        fputcsv($fp, ['Unresolved', $stats['unresolved']]);
        fputcsv($fp, []);

        // Write severity breakdown
        fputcsv($fp, ['Severity', 'Count']);
        foreach ($stats['by_severity'] as $severity => $count) {
            fputcsv($fp, [$severity, $count]);
        }
        fputcsv($fp, []);

        // Write directive breakdown
        fputcsv($fp, ['Directive', 'Count']);
        foreach ($stats['by_directive'] as $directive => $count) {
            fputcsv($fp, [$directive, $count]);
        }

        fclose($fp);
    }

    /**
     * Format results as plain text.
     *
     * @param array $stats
     * @return string
     */
    private function formatAsText(array $stats): string
    {
        $text = "CSP VIOLATION ANALYSIS REPORT\n";
        $text .= str_repeat("=", 50) . "\n\n";

        $text .= "Period: {$stats['period']['from']} to {$stats['period']['to']}\n";
        $text .= "Total Violations: {$stats['total']}\n";
        $text .= "Unresolved: {$stats['unresolved']}\n\n";

        $text .= "By Severity:\n";
        foreach ($stats['by_severity'] as $severity => $count) {
            $text .= "  {$severity}: {$count}\n";
        }

        $text .= "\nTop Directives:\n";
        foreach ($stats['by_directive'] as $directive => $count) {
            $text .= "  {$directive}: {$count}\n";
        }

        $text .= "\nBy Module:\n";
        foreach ($stats['by_module'] as $module => $count) {
            $text .= "  {$module}: {$count}\n";
        }

        return $text;
    }
}
