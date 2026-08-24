<?php

declare(strict_types=1);

namespace Modules\Timesheets\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Timesheets\Models\TimesheetEntry;
use Modules\Timesheets\Models\TimesheetPeriod;

/**
 * Chantier 8.4: Sheets/Show.vue and Sheets/Form.vue (edit mode) are real,
 * fully-built Inertia pages expecting server-provided `sheet`/`entries`
 * props, but routes/web.php rendered them from bare closures with no
 * props at all — every visit rendered with `sheet` undefined.
 */
class SheetWebController extends Controller
{
    public function show(int $sheet): Response
    {
        $period = TimesheetPeriod::with(['employee', 'submitter', 'approver'])->findOrFail($sheet);

        // Chantier 32.19 (Timesheets deep 14-layer audit): this server-
        // rendered Inertia page had zero authorization call at all —
        // TimesheetPeriodPolicy::view() exists and is correctly written
        // (own period OR admin/manager/hr-manager) but nothing here ever
        // called it, and the web route group only gates on
        // ['auth', 'module:Timesheets'] with no role restriction — any
        // authenticated Timesheets user could view any other employee's
        // full weekly timesheet (hours/task descriptions/notes) just by
        // changing the {sheet} id in the URL, confirmed empirically. The
        // props are embedded in the real server response regardless of
        // what the Vue page chooses to render.
        $this->authorize('view', $period);

        // Chantier 32.19: whereDate() bounds, not whereBetween() on the raw
        // column — see TimesheetAdvancedController::approvePeriod()'s
        // docblock for the full write-up. This page previously silently
        // omitted the period's own last day's entries from the Sheets/
        // Show.vue detail view.
        $entries = TimesheetEntry::with('project:id,name')
            ->where('employee_id', $period->employee_id)
            ->whereDate('entry_date', '>=', $period->period_start)
            ->whereDate('entry_date', '<=', $period->period_end)
            ->orderBy('entry_date')
            ->get()
            ->map(fn (TimesheetEntry $entry) => [
                'id'               => $entry->id,
                'task_description' => $entry->description,
                'work_date'        => $entry->entry_date->format('Y-m-d'),
                'hours'            => (float) $entry->hours_worked,
                'rate'             => (float) $entry->hourly_rate,
                'billable'         => (float) $entry->billable_hours > 0,
                'notes'            => $entry->notes,
                'project'          => $entry->project ? ['id' => $entry->project->id, 'name' => $entry->project->name] : null,
            ]);

        return Inertia::render('Timesheets/Sheets/Show', [
            'sheet'   => [
                'id'              => $period->id,
                'employee_id'     => $period->employee_id,
                'employee'        => $period->employee ? [
                    'id' => $period->employee->id, 'name' => $period->employee->full_name, 'email' => $period->employee->email,
                ] : null,
                'period_start'    => $period->period_start->format('Y-m-d'),
                'period_end'      => $period->period_end->format('Y-m-d'),
                'total_hours'     => (float) $period->total_hours,
                'billable_hours'  => (float) $period->billable_hours,
                'status'          => $period->status,
                'submitted_at'    => $period->submitted_at?->format('Y-m-d H:i:s'),
                'submitter'       => $period->submitter ? ['id' => $period->submitter->id, 'name' => $period->submitter->name] : null,
                'approved_at'     => $period->approved_at?->format('Y-m-d H:i:s'),
                'approver'        => $period->approver ? ['id' => $period->approver->id, 'name' => $period->approver->name] : null,
                'rejected_reason' => $period->rejected_reason,
                'created_at'      => $period->created_at?->format('Y-m-d H:i:s'),
            ],
            'entries' => $entries,
        ]);
    }

    public function edit(int $sheet): Response
    {
        $period = TimesheetPeriod::findOrFail($sheet);

        // Chantier 32.19: same missing-authorize() gap as show() above —
        // 'update' correctly requires ownership + draft status (or an
        // admin/manager override), matching Show.vue's own edit-link
        // v-if="sheet.status === 'draft'".
        $this->authorize('update', $period);

        return Inertia::render('Timesheets/Sheets/Form', [
            'sheet' => [
                'id'           => $period->id,
                'employee_id'  => $period->employee_id,
                'period_start' => $period->period_start->format('Y-m-d'),
                'period_end'   => $period->period_end->format('Y-m-d'),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Timesheets/Sheets/Form');
    }
}
