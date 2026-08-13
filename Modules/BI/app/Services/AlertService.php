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
    public function checkAlert(BiAlert $alert): bool
    {
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
        $alert->update(['last_checked_at' => now()]);
        if ($triggered) {
            $alert->update(['last_triggered_at' => now()]);
        }

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
