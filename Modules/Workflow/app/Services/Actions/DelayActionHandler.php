<?php

declare(strict_types=1);

namespace Modules\Workflow\Services\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * DelayActionHandler — Phase 39
 *
 * Handles delay/pause workflow actions:
 * wait N minutes, wait until datetime, wait for business hours.
 *
 * Business hours are Africa/Paris-timezone aware (Mon-Fri 08:00-18:00).
 * Instead of blocking, these return a 'resume_at' timestamp so the
 * FlowExecutionEngine can re-queue the job at the right time.
 */
class DelayActionHandler
{
    /** Business hours start (hour, 24h) */
    private const BIZ_HOUR_START = 8;

    /** Business hours end (hour, 24h) */
    private const BIZ_HOUR_END = 18;

    /** Default business timezone (Africa + Europe overlap) */
    private const DEFAULT_TIMEZONE = 'Africa/Abidjan';

    /**
     * Dispatch an action by its dot-notation suffix.
     *
     * @param  array<string,mixed>  $params
     * @param  array<string,mixed>  $context
     * @return array<string,mixed>
     */
    public function dispatch(string $action, array $params, array $context): array
    {
        return match ($action) {
            'delay.wait_minutes'       => $this->waitMinutes($params, $context),
            'delay.wait_until'         => $this->waitUntil($params, $context),
            'delay.wait_business_hours' => $this->waitBusinessHours($params, $context),
            default => ['status' => 'skipped', 'reason' => "Unknown Delay action: {$action}"],
        };
    }

    /**
     * action: delay.wait_minutes
     * Pause flow execution for N minutes by returning a resume_at timestamp.
     *
     * @param  array<string,mixed>  $params   e.g. ['minutes' => 30]
     * @param  array<string,mixed>  $context
     * @return array{resume_at: string, delay_minutes: int, status: string}
     */
    public function waitMinutes(array $params, array $context): array
    {
        $minutes = (int) ($params['minutes'] ?? 5);

        if ($minutes <= 0) {
            return ['status' => 'error', 'reason' => 'minutes must be a positive integer'];
        }

        if ($minutes > 10_080) { // 1 week max
            return ['status' => 'error', 'reason' => 'Maximum delay is 10080 minutes (1 week)'];
        }

        $resumeAt = now()->addMinutes($minutes);

        Log::info('WorkflowAction: delay.wait_minutes', [
            'minutes'   => $minutes,
            'resume_at' => $resumeAt->toIso8601String(),
        ]);

        return [
            'resume_at'     => $resumeAt->toIso8601String(),
            'delay_minutes' => $minutes,
            'status'        => 'deferred',
        ];
    }

    /**
     * action: delay.wait_until
     * Pause until a specific datetime.
     *
     * @param  array<string,mixed>  $params   e.g. ['datetime' => '2026-06-01 09:00:00', 'timezone' => 'Africa/Nairobi']
     * @param  array<string,mixed>  $context
     * @return array{resume_at: string, delay_minutes: int, status: string}
     */
    public function waitUntil(array $params, array $context): array
    {
        $datetimeStr = $params['datetime'] ?? ($context['target_date'] ?? null);
        $timezone    = $params['timezone'] ?? self::DEFAULT_TIMEZONE;

        if (! $datetimeStr) {
            return ['status' => 'error', 'reason' => 'Missing datetime parameter'];
        }

        try {
            $target = Carbon::parse($datetimeStr, $timezone);
        } catch (\Throwable) {
            return ['status' => 'error', 'reason' => "Invalid datetime: {$datetimeStr}"];
        }

        if ($target->isPast()) {
            // Already past — resume immediately
            return [
                'resume_at'     => now()->toIso8601String(),
                'delay_minutes' => 0,
                'status'        => 'immediate',
            ];
        }

        $delayMinutes = (int) now()->diffInMinutes($target, false);

        return [
            'resume_at'     => $target->toIso8601String(),
            'delay_minutes' => $delayMinutes,
            'timezone'      => $timezone,
            'status'        => 'deferred',
        ];
    }

    /**
     * action: delay.wait_business_hours
     * Pause until the next business day/hour, skipping nights and weekends.
     * Business hours: Mon-Fri, 08:00-18:00 in Africa/Abidjan (UTC+0) or Africa/Nairobi (UTC+3).
     *
     * @param  array<string,mixed>  $params   e.g. ['timezone' => 'Africa/Nairobi', 'buffer_minutes' => 15]
     * @param  array<string,mixed>  $context
     * @return array{resume_at: string, delay_minutes: int, reason: string, status: string}
     */
    public function waitBusinessHours(array $params, array $context): array
    {
        $timezone      = $params['timezone'] ?? self::DEFAULT_TIMEZONE;
        $bufferMinutes = (int) ($params['buffer_minutes'] ?? 0);

        try {
            $now = Carbon::now($timezone);
        } catch (\Throwable) {
            $now = Carbon::now(self::DEFAULT_TIMEZONE);
            $timezone = self::DEFAULT_TIMEZONE;
        }

        $resumeAt = $this->nextBusinessMoment($now);

        if ($bufferMinutes > 0) {
            $resumeAt->addMinutes($bufferMinutes);
        }

        $delayMinutes = (int) $now->diffInMinutes($resumeAt, false);
        $isImmediate  = $delayMinutes <= 1;

        $reason = $isImmediate
            ? 'Currently within business hours'
            : "Outside business hours — resuming at next business moment";

        return [
            'resume_at'     => $resumeAt->toIso8601String(),
            'delay_minutes' => max(0, $delayMinutes),
            'timezone'      => $timezone,
            'reason'        => $reason,
            'status'        => $isImmediate ? 'immediate' : 'deferred',
        ];
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Find the next moment within business hours (Mon-Fri, 08:00-18:00).
     */
    private function nextBusinessMoment(Carbon $now): Carbon
    {
        $candidate = $now->copy();

        // If we're already within business hours on a weekday, return now
        if ($this->isBusinessHour($candidate)) {
            return $candidate;
        }

        // If past end of day, move to next day start
        if ($candidate->hour >= self::BIZ_HOUR_END) {
            $candidate->addDay()->setHour(self::BIZ_HOUR_START)->setMinute(0)->setSecond(0);
        } elseif ($candidate->hour < self::BIZ_HOUR_START) {
            // Before start of day
            $candidate->setHour(self::BIZ_HOUR_START)->setMinute(0)->setSecond(0);
        }

        // Skip weekends (6=Saturday, 7=Sunday in Carbon)
        $attempts = 0;
        while (! $this->isBusinessDay($candidate) && $attempts < 7) {
            $candidate->addDay()->setHour(self::BIZ_HOUR_START)->setMinute(0)->setSecond(0);
            $attempts++;
        }

        return $candidate;
    }

    private function isBusinessHour(Carbon $dt): bool
    {
        return $this->isBusinessDay($dt)
            && $dt->hour >= self::BIZ_HOUR_START
            && $dt->hour < self::BIZ_HOUR_END;
    }

    private function isBusinessDay(Carbon $dt): bool
    {
        return ! $dt->isWeekend();
    }
}
