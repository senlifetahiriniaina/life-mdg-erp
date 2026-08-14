<?php

namespace Modules\Validation\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\HR\Models\LeaveRequest;
use Modules\HR\Models\ShiftSchedule;
use Modules\Validation\Models\ApprovalHierarchy;
use Modules\Validation\Models\ApprovalRequest;
use Modules\Validation\Models\HierarchyLevel;
use Modules\Validation\Models\LevelApprover;

/**
 * Resolves who should currently act on an ApprovalRequest — the piece that
 * used to be missing entirely (ApprovalRequestService::getNextApprovers()
 * returned an empty collection whenever a workflow had rules). Adds:
 *   - role-based approver resolution (a level's approver can be "whoever
 *     holds role X", not only a specific user_id)
 *   - leave/working-hours awareness: an approver on approved leave today, or
 *     outside their configured ShiftSchedule, is automatically skipped in
 *     favor of their configured backup, then the next hierarchy level, then
 *     the hierarchy's escalation_role — never a silent drop to admin.
 */
class ApprovalRoutingResolver
{
    public function resolveApprovers(ApprovalRequest $request): Collection
    {
        $workflow = $request->workflow;

        if (! $workflow) {
            return $this->adminFallback();
        }

        // Descending rule_order: tiered threshold rules (e.g. amount >= 5000,
        // then amount >= 50000) are defined with rising rule_order as they
        // get more specific/restrictive, so the highest-order matching rule
        // is the most specific one and should win — not the first one an
        // ascending scan happens to satisfy.
        $rule = $workflow->rules()
            ->orderByDesc('rule_order')
            ->get()
            ->first(fn ($rule) => $rule->evaluateCondition($request->approvable));

        $hierarchy = $this->resolveHierarchy($request, $rule);

        if (! $hierarchy) {
            return $this->adminFallback();
        }

        if (! $request->hierarchy_id) {
            $request->update([
                'hierarchy_id' => $hierarchy->id,
                'current_level' => $request->current_level ?: 1,
                'total_levels' => $hierarchy->levels()->count(),
            ]);
        }

        $level = $hierarchy->levels()->where('level_order', $request->current_level ?: 1)->first();

        if (! $level) {
            // Hierarchy exists but has no level configured at this order —
            // an explicit misconfiguration to surface, not a silent fallback.
            return collect();
        }

        return $this->resolveLevel($request, $hierarchy, $level);
    }

    protected function resolveHierarchy(ApprovalRequest $request, $rule): ?ApprovalHierarchy
    {
        if ($rule?->hierarchy) {
            return $rule->hierarchy;
        }

        if ($request->hierarchy_id) {
            return $request->hierarchy;
        }

        return ApprovalHierarchy::where('module_name', $request->workflow->module_name)
            ->where('is_active', true)
            ->first();
    }

    protected function resolveLevel(ApprovalRequest $request, ApprovalHierarchy $hierarchy, HierarchyLevel $level): Collection
    {
        $today = now();
        $resolved = collect();

        foreach ($level->activeApprovers()->get() as $levelApprover) {
            foreach ($levelApprover->resolvesToUsers() as $approver) {
                if ($this->isAvailable($approver, $today)) {
                    $resolved->push($approver);

                    continue;
                }

                $backups = $levelApprover->resolvesToBackupUsers();

                if ($backups->isNotEmpty()) {
                    $resolved = $resolved->merge($backups);
                    $this->recordEscalation($request, $approver, $backups->first(), $this->unavailabilityReason($approver, $today));

                    continue;
                }

                $nextLevel = $hierarchy->levels()->where('level_order', $level->level_order + 1)->first();

                if ($nextLevel) {
                    $request->update(['current_level' => $nextLevel->level_order]);

                    return $this->resolveLevel($request, $hierarchy, $nextLevel);
                }

                if ($hierarchy->escalation_role) {
                    $catchAll = User::role($hierarchy->escalation_role)->get();
                    $resolved = $resolved->merge($catchAll);
                    $this->recordEscalation($request, $approver, null, 'no_backup_configured');

                    continue;
                }

                // No backup, no next level, no catch-all role: leave the
                // request assigned to the unavailable approver rather than
                // silently reassigning to admin — this should surface as an
                // operational alert, not disappear.
                $resolved->push($approver);
            }
        }

        return $resolved->unique('id')->values();
    }

    /**
     * An approver is available unless they're on approved leave today, or
     * they have an active ShiftSchedule and right now falls outside it.
     * Fails open (available=true) whenever there's no HR record, no leave
     * data, or no shift configured — see the migration for hr_shift_schedules
     * for why: the table starts empty, and defaulting to "unavailable" would
     * auto-escalate every single approval the moment this ships.
     */
    public function isAvailable(User $approver, Carbon $date): bool
    {
        $employee = $approver->employee;

        if (! $employee) {
            return true;
        }

        if (LeaveRequest::approvedAndCoveringDate($employee->id, $date)->exists()) {
            return false;
        }

        $shifts = ShiftSchedule::where('employee_id', $employee->id)
            ->where('status', 'active')
            ->get();

        if ($shifts->isEmpty()) {
            return true;
        }

        $nowForApprover = $date->clone()->setTimezone($approver->timezone ?? config('app.timezone'));

        return $shifts->contains(function (ShiftSchedule $shift) use ($nowForApprover) {
            if (! $shift->isActive() || ! $shift->getWorksOnDay($nowForApprover->dayOfWeek)) {
                return false;
            }

            $start = $shift->start_time->format('H:i:s');
            $end = $shift->end_time->format('H:i:s');
            $now = $nowForApprover->format('H:i:s');

            return $now >= $start && $now <= $end;
        });
    }

    protected function unavailabilityReason(User $approver, Carbon $date): string
    {
        $employee = $approver->employee;

        if ($employee && LeaveRequest::approvedAndCoveringDate($employee->id, $date)->exists()) {
            return 'leave';
        }

        return 'outside_hours';
    }

    protected function recordEscalation(ApprovalRequest $request, User $from, ?User $to, string $reason): void
    {
        $request->update([
            'escalated_from_id' => $from->id,
            'approver_id' => $to?->id ?? $request->approver_id,
            'escalation_reason' => $reason,
        ]);
    }

    protected function adminFallback(): Collection
    {
        return User::role('admin')->get();
    }
}
