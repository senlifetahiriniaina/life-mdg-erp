<?php

namespace Modules\Timesheets\Services;

use Illuminate\Database\Eloquent\Collection;
use Modules\Timesheets\Models\TimeAllocation;
use Modules\Timesheets\Models\TimesheetEntry;
use Modules\Timesheets\Models\TimeTrackingProject;

class TimesheetService
{
    public function createEntry(
        int $employee_id,
        $entry_date,
        float $hours_worked,
        string $description,
        ?int $task_id = null,
        ?string $notes = null
    ): TimesheetEntry {
        return TimesheetEntry::create([
            'employee_id' => $employee_id,
            'entry_date' => $entry_date,
            'hours_worked' => $hours_worked,
            'description' => $description,
            'task_id' => $task_id,
            'notes' => $notes,
            'status' => 'draft',
        ]);
    }

    public function updateEntry(
        TimesheetEntry $entry,
        ?float $hours_worked = null,
        ?string $description = null,
        ?int $task_id = null,
        ?string $notes = null
    ): TimesheetEntry {
        if (! $entry->canEdit()) {
            throw new \Exception('Cannot edit submitted or approved timesheets');
        }

        $entry->update(array_filter([
            'hours_worked' => $hours_worked,
            'description' => $description,
            'task_id' => $task_id,
            'notes' => $notes,
        ], fn ($v) => $v !== null));

        return $entry->refresh();
    }

    public function submitEntry(TimesheetEntry $entry): TimesheetEntry
    {
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

    public function createTrackingProject(
        string $name,
        string $code,
        ?string $description,
        float $budget_hours,
        ?int $department_id,
        $start_date,
        $end_date
    ): TimeTrackingProject {
        return TimeTrackingProject::create([
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
