<?php

namespace Modules\Accounting\Services;

use Carbon\Carbon;
use Modules\Timesheets\Models\TimeAllocation;
use Modules\Timesheets\Models\TimesheetEntry;

class LaborCostAllocationService
{
    // ────────────────────────────────────────────────────────────────────────
    // COST ALLOCATION FROM TIMESHEETS
    // ────────────────────────────────────────────────────────────────────────

    public function allocateTimesheetCosts(
        TimesheetEntry $entry,
        ?string $journal_reference = null
    ): array {
        if (! $entry->allocations) {
            $entry->load('allocations');
        }

        $costRecords = [];

        foreach ($entry->allocations as $allocation) {
            $costRecord = $this->createCostRecord(
                entry_id: $entry->id,
                allocation_id: $allocation->id,
                project_id: $allocation->project_id,
                cost_center_id: $allocation->cost_center_id,
                task_id: $allocation->task_id,
                hours: $allocation->hours,
                hourly_rate: $allocation->hourly_rate,
                amount: $allocation->cost_amount,
                is_billable: $allocation->is_billable,
                journal_reference: $journal_reference
            );

            $costRecords[] = $costRecord;
        }

        return $costRecords;
    }

    private function createCostRecord(
        int $entry_id,
        int $allocation_id,
        ?int $project_id,
        ?int $cost_center_id,
        ?int $task_id,
        float $hours,
        float $hourly_rate,
        float $amount,
        bool $is_billable,
        ?string $journal_reference = null
    ): array {
        // Create accounting journal entries
        $entries = [];

        // Labor cost entry (debit to project/cost center, credit to payroll)
        if ($is_billable && $project_id) {
            $entries[] = [
                'account' => 'labor_project_cost',
                'cost_center_id' => $cost_center_id,
                'project_id' => $project_id,
                'amount' => $amount,
                'type' => 'debit',
                'description' => "Labor allocation: {$hours}h @ {$hourly_rate}/h",
                'reference' => $journal_reference,
            ];
        } else {
            $entries[] = [
                'account' => 'labor_overhead_cost',
                'cost_center_id' => $cost_center_id,
                'amount' => $amount,
                'type' => 'debit',
                'description' => "Overhead labor: {$hours}h @ {$hourly_rate}/h",
                'reference' => $journal_reference,
            ];
        }

        // Payroll credit
        $entries[] = [
            'account' => 'payroll_accrual',
            'amount' => $amount,
            'type' => 'credit',
            'description' => 'Labor cost accrual',
            'reference' => $journal_reference,
        ];

        return [
            'timesheet_entry_id' => $entry_id,
            'allocation_id' => $allocation_id,
            'project_id' => $project_id,
            'cost_center_id' => $cost_center_id,
            'task_id' => $task_id,
            'hours' => $hours,
            'hourly_rate' => $hourly_rate,
            'total_amount' => $amount,
            'is_billable' => $is_billable,
            'journal_entries' => $entries,
            'created_at' => now(),
        ];
    }

    public function calculateProjectCosts(int $project_id, ?Carbon $from_date = null, ?Carbon $to_date = null): array
    {
        $query = TimeAllocation::where('project_id', $project_id);

        if ($from_date) {
            $query->whereHas('entry', fn ($q) => $q->whereDate('entry_date', '>=', $from_date));
        }

        if ($to_date) {
            $query->whereHas('entry', fn ($q) => $q->whereDate('entry_date', '<=', $to_date));
        }

        $allocations = $query->with('entry')->get();

        $totalCost = 0;
        $billableCost = 0;
        $totalHours = 0;
        $billableHours = 0;

        foreach ($allocations as $allocation) {
            $cost = $allocation->cost_amount ?? 0;
            $totalCost += $cost;
            $totalHours += $allocation->hours;

            if ($allocation->is_billable) {
                $billableCost += $cost;
                $billableHours += $allocation->hours;
            }
        }

        return [
            'project_id' => $project_id,
            'period' => [
                'from' => $from_date?->format('Y-m-d'),
                'to' => $to_date?->format('Y-m-d'),
            ],
            'total_hours' => $totalHours,
            'billable_hours' => $billableHours,
            'non_billable_hours' => $totalHours - $billableHours,
            'total_cost' => round($totalCost, 2),
            'billable_cost' => round($billableCost, 2),
            'non_billable_cost' => round($totalCost - $billableCost, 2),
            'average_rate' => $totalHours > 0 ? round($totalCost / $totalHours, 2) : 0,
        ];
    }

    public function calculateCostCenterCosts(int $cost_center_id, ?Carbon $from_date = null, ?Carbon $to_date = null): array
    {
        $query = TimeAllocation::where('cost_center_id', $cost_center_id);

        if ($from_date) {
            $query->whereHas('entry', fn ($q) => $q->whereDate('entry_date', '>=', $from_date));
        }

        if ($to_date) {
            $query->whereHas('entry', fn ($q) => $q->whereDate('entry_date', '<=', $to_date));
        }

        $allocations = $query->get();

        $totalCost = $allocations->sum('cost_amount');
        $totalHours = $allocations->sum('hours');
        $byProject = $allocations->groupBy('project_id')->map(fn ($group) => [
            'project_id' => $group->first()->project_id,
            'hours' => $group->sum('hours'),
            'cost' => round($group->sum('cost_amount'), 2),
        ]);

        return [
            'cost_center_id' => $cost_center_id,
            'period' => [
                'from' => $from_date?->format('Y-m-d'),
                'to' => $to_date?->format('Y-m-d'),
            ],
            'total_hours' => $totalHours,
            'total_cost' => round($totalCost, 2),
            'by_project' => $byProject->values()->toArray(),
            'average_hourly_cost' => $totalHours > 0 ? round($totalCost / $totalHours, 2) : 0,
        ];
    }

    public function getTimesheetCostSummary(?Carbon $from_date = null, ?Carbon $to_date = null): array
    {
        $query = TimeAllocation::query();

        if ($from_date) {
            $query->whereHas('entry', fn ($q) => $q->whereDate('entry_date', '>=', $from_date));
        }

        if ($to_date) {
            $query->whereHas('entry', fn ($q) => $q->whereDate('entry_date', '<=', $to_date));
        }

        $allocations = $query->with('entry')->get();

        $totalCost = $allocations->sum('cost_amount');
        $totalHours = $allocations->sum('hours');
        $billableCost = $allocations->where('is_billable', true)->sum('cost_amount');
        $billableHours = $allocations->where('is_billable', true)->sum('hours');

        $byEmployee = $allocations->groupBy('entry.employee_id')->map(fn ($group) => [
            'employee_id' => $group->first()->entry->employee_id,
            'hours' => $group->sum('hours'),
            'cost' => round($group->sum('cost_amount'), 2),
        ]);

        return [
            'period' => [
                'from' => $from_date?->format('Y-m-d'),
                'to' => $to_date?->format('Y-m-d'),
            ],
            'total_hours' => $totalHours,
            'billable_hours' => $billableHours,
            'total_cost' => round($totalCost, 2),
            'billable_cost' => round($billableCost, 2),
            'billable_percentage' => $totalHours > 0 ? round(($billableHours / $totalHours) * 100, 2) : 0,
            'by_employee' => $byEmployee->values()->toArray(),
        ];
    }
}
