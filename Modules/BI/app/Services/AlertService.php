<?php

declare(strict_types=1);

namespace Modules\BI\Services;

use Illuminate\Support\Collection;
use Modules\BI\Models\AlertEvent;
use Modules\BI\Models\BiAlert;
use Modules\BI\Models\KpiAlert;
use Modules\BI\Models\ScheduledReport;

class AlertService
{
    public function __construct(private readonly QueryRunnerService $queryRunner) {}

    /**
     * Chantier 32.24 (BI 14-layer audit, layer 8 — validation métier): before this
     * fix, `bi_alerts.last_value` was never written by any live code path — the
     * only writer was `EvaluateAlertRulesJob::fetchMetricValue()`, a job confirmed
     * dead/fake (rand()-based) and deleted in this same chantier — so `checkAlert()`
     * always short-circuited to `false` on a freshly created alert and
     * `AlertController::test()` was permanently non-functional. For a query-backed
     * alert (`query_id` set), this resolves a real current value via the already-
     * live, tested `QueryRunnerService::runQuery()` — the first numeric value found
     * in the query's first row, preferring a column matching `metric_name` when
     * present. Widget-backed alerts (`widget_id` set) have no equivalent real
     * "resolve this widget's current value" mechanism anywhere in this module
     * (`DrillDownService::getWidgetBaseData()` is itself a documented stub
     * returning `[]`, per the Chantier 29 changelog entry) — building one would be
     * new business logic, not a wiring fix, so it stays a documented gap: the
     * alert's `last_value` is left untouched and `checkAlert()` falls back to its
     * pre-existing behaviour for that case.
     */
    public function refreshValue(BiAlert $alert): ?float
    {
        if ($alert->query_id === null) {
            return null;
        }

        $query = $alert->biQuery;
        if ($query === null) {
            return null;
        }

        try {
            $result = $this->queryRunner->runQuery($query);
        } catch (\Throwable) {
            return null;
        }

        if ($result['rows'] === []) {
            return null;
        }

        $row = $result['rows'][0];

        if ($alert->metric_name !== '' && array_key_exists($alert->metric_name, $row) && is_numeric($row[$alert->metric_name])) {
            return (float) $row[$alert->metric_name];
        }

        foreach ($row as $value) {
            if (is_numeric($value)) {
                return (float) $value;
            }
        }

        return null;
    }

    public function checkAlert(BiAlert $alert): bool
    {
        $refreshed = $this->refreshValue($alert);
        if ($refreshed !== null) {
            $alert->last_value = $refreshed;
        }

        $value = $alert->last_value;
        if ($value === null) {
            return false;
        }
        $triggered = match ($alert->condition_type) {
            'above' => $value > $alert->threshold,
            'below' => $value < $alert->threshold,
            'equals' => abs($value - $alert->threshold) < PHP_FLOAT_EPSILON,
            'change_pct' => abs($value) >= $alert->threshold,
            default => false,
        };
        $alert->last_checked_at = now();
        if ($triggered) {
            $alert->last_triggered_at = now();
        }
        // Explicit save (not update([...])) so the refreshed last_value assigned
        // above is guaranteed to persist alongside last_checked_at/last_triggered_at
        // in the same write, rather than relying on it merely being "dirty".
        $alert->save();

        return $triggered;
    }

    public function createAlert(array $data): KpiAlert
    {
        return KpiAlert::create($data);
    }

    public function evaluateAlert(KpiAlert $alert, float $currentValue): ?AlertEvent
    {
        if (! $alert->isActive()) {
            return null;
        }
        if (! $alert->evaluate($currentValue)) {
            return null;
        }
        $alert->trigger($currentValue);

        return AlertEvent::create([
            'alert_id' => $alert->id,
            'triggered_value' => $currentValue,
            'threshold' => $alert->threshold,
            'message' => "{$alert->name}: value {$currentValue} triggered condition {$alert->condition} {$alert->threshold}",
            'severity' => $alert->severity,
            'acknowledged' => false,
        ]);
    }

    public function checkAlerts(array $metricValues): Collection
    {
        $events = collect();
        $alerts = KpiAlert::where('is_active', true)->get();
        foreach ($alerts as $alert) {
            $value = $metricValues[$alert->metric_name] ?? null;
            if ($value !== null) {
                $event = $this->evaluateAlert($alert, (float) $value);
                if ($event) {
                    $events->push($event);
                }
            }
        }

        return $events;
    }

    public function acknowledgeEvent(AlertEvent $event, int $userId): void
    {
        $event->acknowledge($userId);
    }

    public function getUnacknowledgedEvents(): Collection
    {
        return AlertEvent::where('acknowledged', false)
            ->with('alert')
            ->orderByDesc('created_at')
            ->get();
    }

    public function createScheduledReport(array $data): ScheduledReport
    {
        $report = ScheduledReport::create($data);
        $report->update(['next_send_at' => $report->computeNextSend()]);

        return $report->fresh();
    }

    public function sendReport(ScheduledReport $report): void
    {
        $report->markSent();
    }

    public function processDueReports(): Collection
    {
        $reports = ScheduledReport::where('is_active', true)
            ->where('next_send_at', '<=', now())
            ->get();

        $sent = collect();
        foreach ($reports as $report) {
            $this->sendReport($report);
            $sent->push($report);
        }

        return $sent;
    }
}
