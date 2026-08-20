<?php

declare(strict_types=1);

namespace Modules\Timesheets\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Timer Service
 *
 * Manages live start/stop timers for timesheet entries.
 * Active timer state is stored in cache (Redis/DB) keyed by user ID.
 * On stop, a TimesheetEntry record is created via TimesheetService.
 */
class TimerService
{
    private const TIMER_TTL_HOURS = 24;
    private const CACHE_PREFIX    = 'timesheet_timer:';

    public function __construct(private TimesheetService $timesheetService) {}

    /**
     * Start a timer for the authenticated user.
     *
     * @param  int         $userId
     * @param  array{project_id?: int, task_id?: int, description?: string} $context
     * @return array{timer_id: string, started_at: string, project_id: int|null}
     * @throws \RuntimeException if a timer is already running
     */
    public function start(int $userId, array $context = []): array
    {
        if ($this->hasActiveTimer($userId)) {
            throw new \RuntimeException("A timer is already running. Stop it before starting a new one.");
        }

        $timerId = uniqid('tmr_', true);
        $state   = [
            'timer_id'    => $timerId,
            'user_id'     => $userId,
            'started_at'  => now()->toIso8601String(),
            'project_id'  => $context['project_id'] ?? null,
            'task_id'     => $context['task_id']    ?? null,
            'description' => $context['description'] ?? '',
        ];

        Cache::put(
            $this->cacheKey($userId),
            $state,
            now()->addHours(self::TIMER_TTL_HOURS)
        );

        return $state;
    }

    /**
     * Stop the running timer and persist a timesheet entry.
     *
     * @return array{timer_id: string, duration_minutes: int, entry_id: int|null}
     * @throws \RuntimeException if no timer is running
     */
    public function stop(int $userId): array
    {
        $state = $this->getActiveTimer($userId);
        if (!$state) {
            throw new \RuntimeException("No active timer found for this user.");
        }

        $startedAt       = new \DateTime($state['started_at']);
        $now             = new \DateTime();
        $durationSeconds = $now->getTimestamp() - $startedAt->getTimestamp();
        $durationMinutes = (int) ceil($durationSeconds / 60);

        Cache::forget($this->cacheKey($userId));

        // Persist a timesheet entry using the existing TimesheetService API.
        //
        // Chantier 19 (Lot 2): this passed the raw $userId (a users.id)
        // straight through as employee_id — but TimesheetEntry.employee_id
        // FKs to hr_employees.id, a different ID space (the same mismatch
        // pattern already fixed in TimesheetEntryPolicy/
        // StoreTimesheetEntryRequest/TimesheetEntryController::index()).
        // Confirmed empirically: every browser-timer stop silently created
        // an entry under the wrong employee_id — invisible on the real
        // owner's own "My Sheets"/entries list, and a real collision risk
        // if that raw users.id happens to match a *different* employee's
        // hr_employees.id, silently contaminating their timesheet.
        $entryId = null;
        $user    = User::find($userId);
        $employeeId = $user?->employee?->id;

        if ($employeeId !== null) {
            try {
                $entry   = $this->timesheetService->createEntry(
                    employee_id: $employeeId,
                    entry_date:  $startedAt->format('Y-m-d'),
                    hours_worked: round($durationSeconds / 3600, 4),
                    description: $state['description'] ?: 'Browser timer entry',
                    task_id:     $state['task_id'] ?? null,
                    notes:       'source:browser_timer; timer_id:' . $state['timer_id'],
                    project_id:  $state['project_id'] ?? null,
                    tenant_id:   $user?->company_id,
                );
                $entryId = $entry->id ?? null;
            } catch (\Throwable) {
                // Best-effort — stop response is returned even if entry creation fails
            }
        }

        return [
            'timer_id'         => $state['timer_id'],
            'started_at'       => $state['started_at'],
            'stopped_at'       => $now->format('c'),
            'duration_seconds' => $durationSeconds,
            'duration_minutes' => $durationMinutes,
            'entry_id'         => $entryId,
        ];
    }

    /**
     * Return the active timer state (or null if none running).
     */
    public function getActiveTimer(int $userId): ?array
    {
        return Cache::get($this->cacheKey($userId));
    }

    public function hasActiveTimer(int $userId): bool
    {
        return Cache::has($this->cacheKey($userId));
    }

    /**
     * Force-clear a timer (e.g. on session invalidation).
     */
    public function clear(int $userId): void
    {
        Cache::forget($this->cacheKey($userId));
    }

    private function cacheKey(int $userId): string
    {
        return self::CACHE_PREFIX . $userId;
    }
}
