<?php

namespace Modules\Timesheets\Services;

use Illuminate\Database\Eloquent\Collection;
use Modules\Timesheets\Models\TimeAllocation;
use Modules\Timesheets\Models\TimesheetEntry;
use Modules\Timesheets\Models\TimeTrackingProject;

class TimesheetService
{
    /**
     * Chantier 19 (Lot 2): project_id/billable_hours/hourly_rate/tenant_id
     * were never accepted here at all — every entry created through the
     * real TimesheetEntryController::store() endpoint (the only real
     * write path into this table) silently dropped the caller's project
     * selection and billable/rate input, and never populated tenant_id.
     * The latter isn't cosmetic: TimesheetAdvancedController::utilization()
     * filters TimesheetEntry::where('tenant_id', $companyId) — with every
     * real entry's tenant_id left null, that endpoint has always returned
     * an empty (not erroring, just silently wrong) result set.
     */
    public function createEntry(
        int $employee_id,
        $entry_date,
        float $hours_worked,
        string $description,
        ?int $task_id = null,
        ?string $notes = null,
        ?int $project_id = null,
        ?float $billable_hours = null,
        ?float $hourly_rate = null,
        ?int $tenant_id = null
    ): TimesheetEntry {
        return TimesheetEntry::create([
            'tenant_id' => $tenant_id,
            'employee_id' => $employee_id,
            'entry_date' => $entry_date,
            'hours_worked' => $hours_worked,
            'description' => $description,
            'task_id' => $task_id,
            'project_id' => $project_id,
            // billable_hours is a NOT NULL column (DB default 0) —
            // passing a raw null bypasses the column default entirely and
            // violates the constraint, confirmed empirically while running
            // this session's regression suite.
            'billable_hours' => $billable_hours ?? 0.0,
            'hourly_rate' => $hourly_rate,
            'notes' => $notes,
            'status' => 'draft',
        ]);
    }

    public function updateEntry(
        TimesheetEntry $entry,
        ?float $hours_worked = null,
        ?string $description = null,
        ?int $task_id = null,
        ?string $notes = null,
        ?int $project_id = null,
        ?float $billable_hours = null,
        ?float $hourly_rate = null
    ): TimesheetEntry {
        if (! $entry->canEdit()) {
            throw new \Exception('Cannot edit submitted or approved timesheets');
        }

        $entry->update(array_filter([
            'hours_worked' => $hours_worked,
            'description' => $description,
            'task_id' => $task_id,
            'notes' => $notes,
            'project_id' => $project_id,
            'billable_hours' => $billable_hours,
            'hourly_rate' => $hourly_rate,
        ], fn ($v) => $v !== null));

        return $entry->refresh();
    }

    public function submitEntry(TimesheetEntry $entry): TimesheetEntry
    {
        // Chantier 32.19 (Timesheets deep 14-layer audit, layer 8 —
        // business validation): had no status guard at all — an
        // admin/manager (the only callers who pass
        // TimesheetEntryPolicy::update()'s status-restricted branch on a
        // non-draft entry — an ordinary owner is already blocked there)
        // could call submit() on an already-approved entry and silently
        // revert it back to 'submitted', destroying the approval with no
        // trace of who approved it or when.
        if ($entry->status === 'approved') {
            throw new \Exception('Cannot re-submit an already approved timesheet entry.');
        }

        $entry->update([
            'status' => 'submitted',
            'submitted_by' => auth()->id(),
            'submitted_at' => now(),
        ]);

        return $entry->refresh();
    }

    public function approveEntry(
        TimesheetEntry $entry,
        int $approved_by,
        ?string $notes = null
    ): TimesheetEntry {
        $entry->update([
            'status' => 'approved',
            'approved_by' => $approved_by,
            'approved_at' => now(),
            'notes' => $notes ?? $entry->notes,
        ]);

        return $entry->refresh();
    }

    public function rejectEntry(
        TimesheetEntry $entry,
        int $rejected_by,
        ?string $notes = null
    ): TimesheetEntry {
        $entry->update([
            'status' => 'rejected',
            'approved_by' => $rejected_by,
            'notes' => $notes ?? $entry->notes,
        ]);

        return $entry->refresh();
    }

    public function getPendingApprovals(?int $department_id = null, int $limit = 50): Collection
    {
        $query = TimesheetEntry::where('status', 'submitted')
            ->with(['employee', 'project']);

        if ($department_id) {
            $query->whereHas('employee', fn ($q) => $q->where('department_id', $department_id));
        }

        return $query->limit($limit)->get();
    }

    public function getEmployeeTimesheets(
        int $employee_id,
        ?string $from_date = null,
        ?string $to_date = null
    ): Collection {
        $query = TimesheetEntry::where('employee_id', $employee_id);

        if ($from_date) {
            $query->whereDate('entry_date', '>=', $from_date);
        }

        if ($to_date) {
            $query->whereDate('entry_date', '<=', $to_date);
        }

        return $query->orderByDesc('entry_date')->get();
    }

    public function allocateTime(int $entry_id, array $allocations): array
    {
        $entry = TimesheetEntry::findOrFail($entry_id);

        $entry->allocations()->delete();

        $totalHours = 0;
        $created = [];

        foreach ($allocations as $allocation) {
            $hours = $allocation['hours'];
            $allocation['entry_id'] = $entry_id;
            $allocation['cost_amount'] = ($allocation['hourly_rate'] ?? 0) * $hours;

            $created[] = TimeAllocation::create($allocation);
            $totalHours += $hours;
        }

        if (abs($totalHours - $entry->hours_worked) > 0.01) {
            throw new \InvalidArgumentException(
                "Allocated hours ({$totalHours}) do not match timesheet hours ({$entry->hours_worked})"
            );
        }

        return $created;
    }

    /**
     * Chantier 32.19 (Timesheets deep 14-layer audit): tenant_id is a real,
     * fillable TimeTrackingProject column that was never populated here —
     * confirmed empirically that every tracking project ever created
     * through the real API landed with a null tenant_id, and
     * TrackingProjectController::index() never filtered by it either, so
     * any authenticated employee/manager/admin of ANY company could list,
     * view, and edit every other company's tracking projects. Threaded
     * through to match the same "populate + filter on the real value"
     * pattern already established for TimesheetEntry.tenant_id.
     */
    public function createTrackingProject(
        string $name,
        string $code,
        ?string $description,
        float $budget_hours,
        ?int $department_id,
        $start_date,
        $end_date,
        ?int $tenant_id = null
    ): TimeTrackingProject {
        return TimeTrackingProject::create([
            'tenant_id' => $tenant_id,
            'name' => $name,
            'code' => $code,
            'description' => $description,
            'budget_hours' => $budget_hours,
            'department_id' => $department_id,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'status' => 'active',
            'hours_tracked' => 0,
        ]);
    }

    public function getProjectTimesheets(
        int $project_id,
        ?string $from_date = null,
        ?string $to_date = null
    ): Collection {
        $query = TimesheetEntry::where('project_id', $project_id);

        if ($from_date) {
            $query->whereDate('entry_date', '>=', $from_date);
        }

        if ($to_date) {
            $query->whereDate('entry_date', '<=', $to_date);
        }

        return $query->get();
    }

    public function getTimesheetMetrics(
        int $employee_id,
        ?string $from_date = null,
        ?string $to_date = null
    ): array {
        $query = TimesheetEntry::where('employee_id', $employee_id);

        if ($from_date) {
            $query->whereDate('entry_date', '>=', $from_date);
        }

        if ($to_date) {
            $query->whereDate('entry_date', '<=', $to_date);
        }

        $entries = $query->get();

        $approvedEntries = $entries->where('status', 'approved');

        return [
            'total_entries' => $entries->count(),
            'total_hours' => $approvedEntries->sum('hours_worked'),
            'billable_hours' => $approvedEntries->sum('hours_worked'),
            'total_cost' => 0,
        ];
    }

    public function getProjectMetrics(int $project_id): array
    {
        $project = TimeTrackingProject::findOrFail($project_id);
        $entries = TimesheetEntry::where('project_id', $project_id)
            ->where('status', 'approved')
            ->get();

        $totalHours = $entries->sum('hours_worked');

        return [
            'total_hours' => $totalHours,
            'budget_hours' => $project->budget_hours,
            'remaining_hours' => $project->budget_hours - $totalHours,
            'percentage_used' => $project->budget_hours > 0 ? round(($totalHours / $project->budget_hours) * 100, 2) : 0,
            'is_over_budget' => $totalHours > $project->budget_hours,
        ];
    }
}
