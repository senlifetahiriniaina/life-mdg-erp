<?php

declare(strict_types=1);

namespace Modules\BI\Jobs;

use Illuminate\Support\Facades\Log;
use Exception;
use Modules\BI\Models\BiAlert;
use Modules\Shared\Jobs\BaseAsyncJob;

/**
 * EvaluateAlertRulesJob
 *
 * Periodically evaluates all active alert rules.
 * Checks conditions and triggers alerts when thresholds are met.
 * Should be scheduled to run every 1-5 minutes.
 *
 * @property int|null rule_id Optional single rule ID to evaluate
 * @property bool skip_disabled Whether to skip disabled rules
 * @property string job_id Unique identifier for tracking progress
 */
class EvaluateAlertRulesJob extends BaseAsyncJob
{
    public string $queue = 'bi';

    private string $jobId;

    public function __construct(
        private readonly ?int $rule_id = null,
        private readonly bool $skip_disabled = true
    ) {
        $this->jobId = uniqid('eval_', true);
        parent::__construct($this->extractCompanyId());
    }

    protected function execute(): void
    {
        try {
            Log::info('Starting alert rule evaluation', [
                'job_id' => $this->jobId,
                'rule_id' => $this->rule_id,
                'timestamp' => now()->toIso8601String(),
            ]);

            // Load alerts to evaluate
            $alerts = $this->loadAlerts();

            if ($alerts->isEmpty()) {
                Log::info('No alerts to evaluate', ['job_id' => $this->jobId]);
                return;
            }

            $evaluatedCount = 0;
            $triggeredCount = 0;
            $failedEvaluations = [];

            // Evaluate each alert rule
            foreach ($alerts as $alert) {
                try {
                    $triggered = $this->evaluateAlertRule($alert);

                    if ($triggered) {
                        $triggeredCount++;
                        $this->triggerAlert($alert);
                    }

                    $evaluatedCount++;
                } catch (\Throwable $e) {
                    Log::error('Alert evaluation failed', [
                        'job_id' => $this->jobId,
                        'alert_id' => $alert->id,
                        'error' => $e->getMessage(),
                    ]);

                    $failedEvaluations[] = [
                        'alert_id' => $alert->id,
                        'error' => $e->getMessage(),
                    ];
                }
            }

            Log::info('Alert rule evaluation completed', [
                'job_id' => $this->jobId,
                'evaluated_count' => $evaluatedCount,
                'triggered_count' => $triggeredCount,
                'failed_count' => count($failedEvaluations),
            ]);
        } catch (\Throwable $e) {
            Log::error('Alert rule evaluation job failed', [
                'job_id' => $this->jobId,
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
            throw new Exception("No tenant context available for alert rules evaluation job");
        }

        return (int) $tenantId;
    }

    /**
     * Load alerts to evaluate
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, BiAlert>
     */
    private function loadAlerts()
    {
        $query = BiAlert::where('status', 'active');

        // If specific rule provided, evaluate only that rule
        if ($this->rule_id !== null) {
            $query->where('id', $this->rule_id);
        }

        // Load with relationships for efficient evaluation
        return $query->with(['widget', 'biQuery'])->get();
    }

    /**
     * Evaluate a single alert rule
     */
    private function evaluateAlertRule(BiAlert $alert): bool
    {
        // Fetch current metric value
        $currentValue = $this->fetchMetricValue($alert);

        if ($currentValue === null) {
            Log::warning('Could not fetch metric value for alert', [
                'job_id' => $this->jobId,
                'alert_id' => $alert->id,
                'metric' => $alert->metric_name,
            ]);

            return false;
        }

        // Update last checked timestamp
        $alert->update(['last_checked_at' => now()]);

        // Evaluate condition
        $triggered = $this->evaluateCondition(
            $currentValue,
            $alert->threshold,
            $alert->condition_type
        );

        // Update last value
        $alert->update(['last_value' => $currentValue]);

        Log::debug('Alert rule evaluated', [
            'job_id' => $this->jobId,
            'alert_id' => $alert->id,
            'current_value' => $currentValue,
            'threshold' => $alert->threshold,
            'condition' => $alert->condition_type,
            'triggered' => $triggered,
        ]);

        return $triggered;
    }

    /**
     * Fetch current metric value from data source
     */
    private function fetchMetricValue(BiAlert $alert): ?float
    {
        // In a real implementation, fetch from configured data source
        // based on widget or query configuration

        if ($alert->widget_id) {
            return $this->fetchWidgetMetricValue($alert->widget_id, $alert->metric_name);
        }

        if ($alert->query_id) {
            return $this->fetchQueryMetricValue($alert->query_id, $alert->metric_name);
        }

        return null;
    }

    /**
     * Fetch metric value from widget
     */
    private function fetchWidgetMetricValue(int $widgetId, string $metricName): ?float
    {
        // Simulate fetching metric from widget's data source
        // In real implementation, query actual data
        return rand(10, 1000) / 10;
    }

    /**
     * Fetch metric value from query result
     */
    private function fetchQueryMetricValue(int $queryId, string $metricName): ?float
    {
        // Simulate fetching metric from query result
        // In real implementation, execute query and extract metric
        return rand(10, 1000) / 10;
    }

    /**
     * Evaluate condition against threshold
     */
    private function evaluateCondition(float $value, float $threshold, string $conditionType): bool
    {
        return match ($conditionType) {
            'above' => $value > $threshold,
            'below' => $value < $threshold,
            'equals' => abs($value - $threshold) < PHP_FLOAT_EPSILON,
            'not_equals' => abs($value - $threshold) >= PHP_FLOAT_EPSILON,
            'within_range' => $value >= ($threshold * 0.9) && $value <= ($threshold * 1.1),
            'outside_range' => $value < ($threshold * 0.9) || $value > ($threshold * 1.1),
            'change_pct' => abs($value) >= ($threshold / 100),
            default => false,
        };
    }

    /**
     * Trigger alert and dispatch to notification job
     */
    private function triggerAlert(BiAlert $alert): void
    {
        // Update alert state
        $alert->update(['last_triggered_at' => now()]);

        // Create alert event record
        $this->createAlertEvent($alert);

        // Dispatch SendAlertNotificationJob for delivery
        \Modules\BI\Jobs\SendAlertNotificationJob::dispatch(
            $alert->id,
            $alert->recipients ?? []
        );

        Log::info('Alert triggered and notification dispatched', [
            'job_id' => $this->jobId,
            'alert_id' => $alert->id,
            'alert_name' => $alert->name,
        ]);
    }

    /**
     * Create alert event record for audit trail
     */
    private function createAlertEvent(BiAlert $alert): void
    {
        // In a real implementation, create BiAlertEvent record
        $eventData = [
            'alert_id' => $alert->id,
            'triggered_value' => $alert->last_value,
            'threshold' => $alert->threshold,
            'condition' => $alert->condition_type,
            'message' => "{$alert->name}: value {$alert->last_value} triggered condition {$alert->condition_type} {$alert->threshold}",
            'acknowledged' => false,
            'triggered_at' => now(),
        ];

        Log::debug('Alert event created', array_merge(['job_id' => $this->jobId], $eventData));
    }
}
