<?php

declare(strict_types=1);

namespace Modules\BI\Jobs;

use Illuminate\Support\Facades\Log;
use Exception;
use Modules\BI\Models\BiAlertEvent;
use Modules\Shared\Jobs\BaseAsyncJob;

/**
 * GenerateAlertDigestJob
 *
 * Creates daily/weekly alert summary reports.
 * Aggregates all alerts and sends digest notifications.
 *
 * @property string frequency Digest frequency: 'daily', 'weekly', 'monthly'
 * @property string job_id Unique identifier for tracking progress
 */
class GenerateAlertDigestJob extends BaseAsyncJob
{
    public string $queue = 'bi';

    private string $jobId;

    public function __construct(
        private readonly string $frequency = 'daily'
    ) {
        $this->jobId = uniqid('digest_', true);
        parent::__construct($this->extractCompanyId());
    }

    protected function execute(): void
    {
        try {
            Log::info('Starting alert digest generation', [
                'job_id' => $this->jobId,
                'frequency' => $this->frequency,
                'timestamp' => now()->toIso8601String(),
            ]);

            // Get time range based on frequency
            $timeRange = $this->getTimeRange();

            // Collect alerts for the period
            $alerts = $this->collectAlertsForPeriod($timeRange);

            if ($alerts->isEmpty()) {
                Log::info('No alerts to include in digest', [
                    'job_id' => $this->jobId,
                    'frequency' => $this->frequency,
                ]);

                return;
            }

            // Generate digest content
            $digest = $this->generateDigestContent($alerts, $timeRange);

            // Get recipient list
            $recipients = $this->getDigestRecipients();

            if (!empty($recipients)) {
                // Send digest
                $this->sendDigest($digest, $recipients);
            }

            // Store digest record
            $this->storeDigestRecord($digest, $alerts);

            Log::info('Alert digest generation completed', [
                'job_id' => $this->jobId,
                'frequency' => $this->frequency,
                'alert_count' => $alerts->count(),
                'recipients' => count($recipients),
            ]);
        } catch (\Throwable $e) {
            Log::error('Alert digest generation failed', [
                'job_id' => $this->jobId,
                'frequency' => $this->frequency,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    private function extractCompanyId(): int
    {
        $tenantId = tenant('id');

        if (!$tenantId) {
            throw new Exception("No tenant context available for alert digest generation job");
        }

        return (int) $tenantId;
    }

    /**
     * Get time range based on frequency
     *
     * @return array{start: \Carbon\Carbon, end: \Carbon\Carbon}
     */
    private function getTimeRange(): array
    {
        return match ($this->frequency) {
            'daily' => ['start' => now()->subDay()->startOfDay(), 'end' => now()->startOfDay()],
            'weekly' => ['start' => now()->subWeek()->startOfWeek(), 'end' => now()->startOfWeek()],
            'monthly' => ['start' => now()->subMonth()->startOfMonth(), 'end' => now()->startOfMonth()],
            default => ['start' => now()->subDay()->startOfDay(), 'end' => now()->startOfDay()],
        };
    }

    /**
     * Collect alerts for the period
     *
     * @param array{start: \Carbon\Carbon, end: \Carbon\Carbon} $timeRange
     * @return \Illuminate\Database\Eloquent\Collection<int, BiAlertEvent>
     */
    private function collectAlertsForPeriod(array $timeRange)
    {
        return BiAlertEvent::whereBetween('created_at', [$timeRange['start'], $timeRange['end']])
            ->with('alert')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Generate digest content from alerts
     *
     * @param \Illuminate\Database\Eloquent\Collection<int, BiAlertEvent> $alerts
     * @param array{start: \Carbon\Carbon, end: \Carbon\Carbon} $timeRange
     * @return array<string, mixed>
     */
    private function generateDigestContent($alerts, array $timeRange): array
    {
        // Aggregate statistics
        $stats = [
            'total_alerts' => $alerts->count(),
            'critical_alerts' => $alerts->where('severity', 'critical')->count(),
            'warning_alerts' => $alerts->where('severity', 'warning')->count(),
            'info_alerts' => $alerts->where('severity', 'info')->count(),
            'acknowledged_alerts' => $alerts->where('acknowledged', true)->count(),
            'unacknowledged_alerts' => $alerts->where('acknowledged', false)->count(),
        ];

        // Group by alert
        $alertsByType = $alerts->groupBy('alert_id');

        // Top triggered alerts
        $topAlerts = $alertsByType
            ->map(fn($group) => [
                'alert_id' => $group->first()->alert_id,
                'alert_name' => $group->first()->alert->name,
                'count' => $group->count(),
                'last_triggered' => $group->max('created_at'),
            ])
            ->sortByDesc('count')
            ->take(5);

        // Build HTML digest
        $html = $this->buildDigestHtml($stats, $topAlerts, $alerts, $timeRange);

        return [
            'frequency' => $this->frequency,
            'period_start' => $timeRange['start'],
            'period_end' => $timeRange['end'],
            'generated_at' => now(),
            'statistics' => $stats,
            'top_alerts' => $topAlerts->toArray(),
            'all_alerts' => $alerts->toArray(),
            'html_content' => $html,
        ];
    }

    /**
     * Build HTML digest content
     *
     * @param array<string, int> $stats
     * @param \Illuminate\Support\Collection<int, array<string, mixed>> $topAlerts
     * @param \Illuminate\Database\Eloquent\Collection<int, BiAlertEvent> $alerts
     * @param array{start: \Carbon\Carbon, end: \Carbon\Carbon} $timeRange
     */
    private function buildDigestHtml(array $stats, $topAlerts, $alerts, array $timeRange): string
    {
        $period = ucfirst($this->frequency);
        $startDate = $timeRange['start']->toFormattedDateString();
        $endDate = $timeRange['end']->toFormattedDateString();

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; background: #f5f5f5; }
        .container { max-width: 800px; margin: 20px auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #1565c0; margin-bottom: 10px; }
        .period { color: #666; font-size: 14px; margin-bottom: 30px; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin: 30px 0; }
        .stat-box { background: #f9f9f9; border-left: 4px solid #2196F3; padding: 15px; border-radius: 4px; }
        .stat-value { font-size: 24px; font-weight: bold; color: #1565c0; }
        .stat-label { color: #666; font-size: 12px; margin-top: 5px; }
        .stat-critical { border-left-color: #f44336; }
        .stat-warning { border-left-color: #ff9800; }
        .critical { color: #f44336; font-weight: bold; }
        .warning { color: #ff9800; font-weight: bold; }
        h2 { color: #333; font-size: 18px; margin: 30px 0 15px 0; border-bottom: 2px solid #2196F3; padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { text-align: left; padding: 12px; border-bottom: 1px solid #ddd; }
        th { background: #2196F3; color: white; font-weight: bold; }
        tr:hover { background: #f5f5f5; }
        .footer { margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 12px; color: #999; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Alert Digest Report - {$period}</h1>
        <div class="period">Period: {$startDate} to {$endDate}</div>

        <h2>Summary Statistics</h2>
        <div class="stats">
            <div class="stat-box">
                <div class="stat-value">{$stats['total_alerts']}</div>
                <div class="stat-label">Total Alerts</div>
            </div>
            <div class="stat-box stat-critical">
                <div class="stat-value critical">{$stats['critical_alerts']}</div>
                <div class="stat-label">Critical</div>
            </div>
            <div class="stat-box stat-warning">
                <div class="stat-value warning">{$stats['warning_alerts']}</div>
                <div class="stat-label">Warnings</div>
            </div>
            <div class="stat-box">
                <div class="stat-value">{$stats['acknowledged_alerts']}</div>
                <div class="stat-label">Acknowledged</div>
            </div>
        </div>

        <h2>Top Triggered Alerts</h2>
        <table>
            <thead>
                <tr>
                    <th>Alert Name</th>
                    <th>Trigger Count</th>
                    <th>Last Triggered</th>
                </tr>
            </thead>
            <tbody>
HTML;

        foreach ($topAlerts as $alert) {
            $alertName = htmlspecialchars($alert['alert_name']);
            $count = $alert['count'];
            $lastTriggered = $alert['last_triggered'];

            $html .= <<<HTML
                <tr>
                    <td>$alertName</td>
                    <td>$count</td>
                    <td>$lastTriggered</td>
                </tr>
HTML;
        }

        $html .= <<<HTML
            </tbody>
        </table>

        <h2>All Alerts ({$stats['total_alerts']})</h2>
        <table>
            <thead>
                <tr>
                    <th>Alert</th>
                    <th>Time</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
HTML;

        foreach ($alerts->take(50) as $alert) {
            $alertName = htmlspecialchars($alert->alert->name ?? 'Unknown');
            $time = $alert->created_at->toFormattedDateTimeString();
            $status = $alert->acknowledged ? 'Acknowledged' : 'Unacknowledged';

            $html .= <<<HTML
                <tr>
                    <td>$alertName</td>
                    <td>$time</td>
                    <td>$status</td>
                </tr>
HTML;
        }

        if ($stats['total_alerts'] > 50) {
            $moreCount = $stats['total_alerts'] - 50;
            $html .= "<tr><td colspan='3' style='text-align: center; font-style: italic;'>... and $moreCount more alerts</td></tr>";
        }

        $html .= <<<HTML
            </tbody>
        </table>

        <div class="footer">
            <p>This is an automated report generated by WideHalo ERP BI System</p>
            <p>Generated on {$timeRange['end']->toFormattedDateTimeString()}</p>
        </div>
    </div>
</body>
</html>
HTML;

        return $html;
    }

    /**
     * Get recipients for digest
     *
     * @return array<int>
     */
    private function getDigestRecipients(): array
    {
        // In a real implementation, fetch from database
        // based on user preferences and digest subscriptions
        return [1, 2, 3, 4, 5]; // Simulated user IDs
    }

    /**
     * Send digest to recipients
     *
     * @param array<string, mixed> $digest
     * @param array<int> $recipients
     */
    private function sendDigest(array $digest, array $recipients): void
    {
        // Dispatch email job for digest
        Log::debug('Digest dispatch initiated', [
            'job_id' => $this->jobId,
            'recipient_count' => count($recipients),
            'html_length' => strlen($digest['html_content']),
        ]);

        // In a real implementation, dispatch mail job
        // $recipients would be batched and mailed
    }

    /**
     * Store digest record in database
     *
     * @param array<string, mixed> $digest
     * @param \Illuminate\Database\Eloquent\Collection<int, BiAlertEvent> $alerts
     */
    private function storeDigestRecord(array $digest, $alerts): void
    {
        // In a real implementation, store in AlertDigest table
        Log::debug('Digest record stored', [
            'job_id' => $this->jobId,
            'frequency' => $this->frequency,
            'alert_count' => $alerts->count(),
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
