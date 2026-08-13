<?php

declare(strict_types=1);

namespace Modules\Workflow\Services\Automation;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Modules\Workflow\Jobs\ExecuteAutomationFlowJob;
use Modules\Workflow\Models\Automation\AutomationFlow;

/**
 * Manages cron-based scheduling for automation flows.
 *
 * Called every minute by the Laravel Scheduler (Kernel::schedule).
 * Supports standard cron expressions — see examples in class constants below.
 */
class FlowSchedulerService
{
    /**
     * Supported cron patterns (documentation reference).
     *
     * '0 8 * * 1-5'   — weekdays at 08:00
     * '0 9 * * 1'     — every Monday at 09:00
     * '0 0 1 * *'     — first of each month (payroll)
     * '* /5 * * * *'  — every 5 minutes (stock monitoring)
     * '0 18 * * 5'    — every Friday at 18:00 (weekly reports)
     */
    private const SCHEDULE_TRIGGER = 'schedule';

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Returns all active flows with schedule triggers that are due to run now.
     *
     * A flow is "due" when its next_run_at <= now() or it has never been run.
     */
    public function getDueFlows(): Collection
    {
        return AutomationFlow::where('trigger_type', self::SCHEDULE_TRIGGER)
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->whereNull('trigger_config->next_run_at')
                    ->orWhere('trigger_config->next_run_at', '<=', now()->toIso8601String());
            })
            ->get();
    }

    /**
     * Parses a cron expression and calculates the next run time from now.
     *
     * @throws \InvalidArgumentException if the cron expression is invalid
     */
    public function getNextRunTime(string $cronExpression): Carbon
    {
        $this->validateCronExpression($cronExpression);

        $parts = preg_split('/\s+/', trim($cronExpression));
        if ($parts === false || count($parts) !== 5) {
            throw new \InvalidArgumentException("Invalid cron expression: {$cronExpression}");
        }

        [$minute, $hour, $dayOfMonth, $month, $dayOfWeek] = $parts;

        $now  = Carbon::now();
        $next = $now->copy()->addMinute()->seconds(0);

        // Iterate minute-by-minute up to 1 year ahead to find next match
        $limit = $now->copy()->addYear();
        while ($next->lessThan($limit)) {
            if (
                $this->matchesCronField($next->minute, $minute) &&
                $this->matchesCronField($next->hour, $hour) &&
                $this->matchesCronField($next->day, $dayOfMonth) &&
                $this->matchesCronField($next->month, $month) &&
                $this->matchesCronField($next->dayOfWeek, $dayOfWeek)
            ) {
                return $next;
            }
            $next->addMinute();
        }

        // Fallback: should not happen for valid expressions
        return $now->addDay();
    }

    /**
     * Registers a flow's schedule by computing and storing next_run_at.
     * Called on flow save / activate.
     */
    public function schedule(AutomationFlow $flow): void
    {
        if ($flow->trigger_type !== self::SCHEDULE_TRIGGER) {
            return;
        }

        $cronExpression = $this->getCronExpression($flow);
        if ($cronExpression === null) {
            Log::warning("FlowSchedulerService: flow {$flow->id} has no cron expression");

            return;
        }

        try {
            $nextRunAt = $this->getNextRunTime($cronExpression);
            $config    = $flow->trigger_config ?? [];
            $config['next_run_at']  = $nextRunAt->toIso8601String();
            $config['cron']         = $cronExpression;
            $config['scheduled_at'] = now()->toIso8601String();

            $flow->update(['trigger_config' => $config]);

            Log::info("FlowSchedulerService: scheduled flow {$flow->id} next run at {$nextRunAt}");
        } catch (\Throwable $e) {
            Log::error("FlowSchedulerService: failed to schedule flow {$flow->id}", [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Removes a flow's schedule by clearing next_run_at.
     * Called on flow deactivate / delete.
     */
    public function unschedule(AutomationFlow $flow): void
    {
        if ($flow->trigger_type !== self::SCHEDULE_TRIGGER) {
            return;
        }

        $config = $flow->trigger_config ?? [];
        unset($config['next_run_at']);
        $config['unscheduled_at'] = now()->toIso8601String();

        $flow->update(['trigger_config' => $config]);

        Log::info("FlowSchedulerService: unscheduled flow {$flow->id}");
    }

    /**
     * Called by Laravel's scheduler every minute.
     * Dispatches all due flows and advances their next_run_at.
     *
     * @return int number of flows dispatched
     */
    public function runDueFlows(): int
    {
        $dueFlows  = $this->getDueFlows();
        $dispatched = 0;

        foreach ($dueFlows as $flow) {
            try {
                // Dispatch the execution job
                ExecuteAutomationFlowJob::dispatch($flow->id, $flow->tenant_id, [
                    'trigger_type'   => 'schedule',
                    'triggered_at'   => now()->toIso8601String(),
                ]);

                // Advance next_run_at to avoid re-triggering before next cycle
                $cronExpression = $this->getCronExpression($flow);
                if ($cronExpression !== null) {
                    $nextRunAt = $this->getNextRunTime($cronExpression);
                    $config    = $flow->trigger_config ?? [];
                    $config['next_run_at']  = $nextRunAt->toIso8601String();
                    $config['last_run_at']  = now()->toIso8601String();
                    $flow->update(['trigger_config' => $config, 'last_run_at' => now()]);
                }

                $dispatched++;

                Log::info("FlowSchedulerService: dispatched flow {$flow->id} ({$flow->name})");
            } catch (\Throwable $e) {
                Log::error("FlowSchedulerService: failed to dispatch flow {$flow->id}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($dispatched > 0) {
            Log::info("FlowSchedulerService: dispatched {$dispatched} scheduled flow(s)");
        }

        return $dispatched;
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function getCronExpression(AutomationFlow $flow): ?string
    {
        $config = $flow->trigger_config ?? [];

        return isset($config['cron']) ? (string) $config['cron'] : null;
    }

    /**
     * Checks whether a value matches a cron field expression.
     * Supports: *, ‹n›, ‹a›-‹b›, ‹a›,‹b›,…, *\/‹step›
     */
    private function matchesCronField(int $value, string $field): bool
    {
        if ($field === '*') {
            return true;
        }

        // Step expression: */n
        if (str_starts_with($field, '*/')) {
            $step = (int) substr($field, 2);

            return $step > 0 && $value % $step === 0;
        }

        // List: a,b,c
        if (str_contains($field, ',')) {
            $values = array_map('intval', explode(',', $field));

            return in_array($value, $values, true);
        }

        // Range: a-b
        if (str_contains($field, '-')) {
            [$start, $end] = array_map('intval', explode('-', $field, 2));

            return $value >= $start && $value <= $end;
        }

        // Exact value
        return (int) $field === $value;
    }

    private function validateCronExpression(string $expression): void
    {
        $parts = preg_split('/\s+/', trim($expression));
        if ($parts === false || count($parts) !== 5) {
            throw new \InvalidArgumentException(
                "Cron expression must have exactly 5 fields (minute hour day month weekday), got: \"{$expression}\""
            );
        }
    }
}
