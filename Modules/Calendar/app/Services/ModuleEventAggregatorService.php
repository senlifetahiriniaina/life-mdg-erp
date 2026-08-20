<?php

declare(strict_types=1);

namespace Modules\Calendar\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Calendar\Models\Calendar;
use Modules\Calendar\Models\CalendarEvent;

/**
 * Pulls events/tasks from other ERP modules and surfaces them in the Calendar.
 *
 * Each module source is opt-in: events are upserted into a dedicated
 * "module" calendar (type='module') per source, so they can be
 * shown/hidden independently.
 */
class ModuleEventAggregatorService
{
    // -----------------------------------------------------------------------
    // Entry point
    // -----------------------------------------------------------------------

    /**
     * Aggregate all module events for the given user and return the total
     * number of events created/updated.
     *
     * @param string[]|null $sources  limit to specific sources (null = all)
     */
    public function aggregateForUser(int $userId, ?array $sources = null): int
    {
        $total = 0;

        $all = [
            'hr_leaves'        => fn () => $this->importHrLeaves($userId),
            'project_tasks'    => fn () => $this->importProjectTasks($userId),
            'helpdesk_sla'     => fn () => $this->importHelpdeskSla($userId),
            'strategy_milestones' => fn () => $this->importStrategyMilestones($userId),
            'manufacturing'    => fn () => $this->importManufacturingOrders($userId),
            'accounting'       => fn () => $this->importAccountingDeadlines($userId),
            'workflow'         => fn () => $this->importWorkflowSchedules($userId),
            'timesheets'       => fn () => $this->importTimesheetEntries($userId),
        ];

        foreach ($all as $key => $fn) {
            if ($sources === null || in_array($key, $sources, true)) {
                try {
                    $total += $fn();
                } catch (\Throwable) {
                    // Silently skip if module table does not exist
                }
            }
        }

        return $total;
    }

    // -----------------------------------------------------------------------
    // HR: approved leaves
    // -----------------------------------------------------------------------

    /**
     * Chantier 19 Lot 3: queried `hr_leaves`, a table that has never existed
     * in this app — confirmed via `Schema::hasTable()` — the real table is
     * `hr_leave_requests`. Silently degraded to 0 events on every call
     * (caught by aggregateForUser()'s own try/catch), invisible because it
     * never threw — this "HR Congés" module calendar has never once shown
     * a real leave, confirmed empirically.
     */
    private function importHrLeaves(int $userId): int
    {
        if (! Schema::hasTable('hr_leave_requests')) {
            return 0;
        }

        $calendar = $this->getOrCreateModuleCalendar($userId, 'HR Congés', '#8B5CF6', 'hr_leaves');
        $synced   = 0;

        $leaves = DB::table('hr_leave_requests')
            ->join('hr_employees', 'hr_leave_requests.employee_id', '=', 'hr_employees.id')
            ->where('hr_employees.user_id', $userId)
            ->where('hr_leave_requests.status', 'approved')
            ->whereNotNull('hr_leave_requests.start_date')
            ->select('hr_leave_requests.*', 'hr_employees.first_name', 'hr_employees.last_name')
            ->get();

        foreach ($leaves as $leave) {
            $title   = "Congé: {$leave->first_name} {$leave->last_name}";
            $startAt = Carbon::parse($leave->start_date)->startOfDay();
            $endAt   = Carbon::parse($leave->end_date ?? $leave->start_date)->endOfDay();

            CalendarEvent::updateOrCreate(
                ['module_type' => 'Leave', 'module_id' => $leave->id, 'calendar_id' => $calendar->id],
                [
                    'title'      => $title,
                    'start_at'   => $startAt,
                    'end_at'     => $endAt,
                    'all_day'    => true,
                    'source'     => 'module',
                    'color'      => '#8B5CF6',
                    'status'     => 'confirmed',
                    'created_by' => $userId,
                ],
            );

            $synced++;
        }

        return $synced;
    }

    // -----------------------------------------------------------------------
    // Projects: tasks with due dates
    // -----------------------------------------------------------------------

    /**
     * Chantier 19 Lot 3: queried `project_tasks` (real table: `prj_tasks`)
     * filtered by an `assigned_to` column that doesn't exist either (real
     * column: `assignee_id`) — a compounding, guaranteed-empty bug: even a
     * naive table-name-only fix would have kept silently failing on the
     * column mismatch (caught by the same outer try/catch, still 0 rows,
     * still invisible). Confirmed both against `Schema::getColumnListing()`.
     */
    private function importProjectTasks(int $userId): int
    {
        if (! Schema::hasTable('prj_tasks')) {
            return 0;
        }

        $calendar = $this->getOrCreateModuleCalendar($userId, 'Tâches Projets', '#0EA5E9', 'project_tasks');
        $synced   = 0;

        $tasks = DB::table('prj_tasks')
            ->where('assignee_id', $userId)
            ->whereNotNull('due_date')
            ->whereIn('status', ['todo', 'in_progress', 'review'])
            ->select('id', 'title', 'due_date', 'status', 'project_id')
            ->get();

        foreach ($tasks as $task) {
            $due     = Carbon::parse($task->due_date);
            $startAt = $due->copy()->startOfDay();
            $endAt   = $due->copy()->endOfDay();

            CalendarEvent::updateOrCreate(
                ['module_type' => 'Task', 'module_id' => $task->id, 'calendar_id' => $calendar->id],
                [
                    'title'      => "Tâche: {$task->title}",
                    'start_at'   => $startAt,
                    'end_at'     => $endAt,
                    'all_day'    => true,
                    'source'     => 'module',
                    'color'      => '#0EA5E9',
                    'status'     => 'confirmed',
                    'created_by' => $userId,
                ],
            );

            $synced++;
        }

        return $synced;
    }

    // -----------------------------------------------------------------------
    // Helpdesk: tickets with SLA deadlines
    // -----------------------------------------------------------------------

    private function importHelpdeskSla(int $userId): int
    {
        if (! Schema::hasTable('hd_tickets')) {
            return 0;
        }

        $calendar = $this->getOrCreateModuleCalendar($userId, 'Helpdesk SLA', '#EF4444', 'helpdesk_sla');
        $synced   = 0;

        $tickets = DB::table('hd_tickets')
            ->where('assignee_id', $userId)
            ->whereNotNull('sla_due_at')
            ->whereNotIn('status', ['resolved', 'closed'])
            ->select('id', 'subject', 'sla_due_at', 'priority', 'status')
            ->get();

        foreach ($tickets as $ticket) {
            $deadline = Carbon::parse($ticket->sla_due_at);

            CalendarEvent::updateOrCreate(
                ['module_type' => 'Ticket', 'module_id' => $ticket->id, 'calendar_id' => $calendar->id],
                [
                    'title'      => "SLA: {$ticket->subject}",
                    'start_at'   => $deadline->copy()->subHour(),
                    'end_at'     => $deadline,
                    'all_day'    => false,
                    'source'     => 'module',
                    'color'      => '#EF4444',
                    'status'     => 'confirmed',
                    'created_by' => $userId,
                ],
            );

            $synced++;
        }

        return $synced;
    }

    // -----------------------------------------------------------------------
    // Strategy: plan milestones
    // -----------------------------------------------------------------------

    /**
     * Chantier 19 Lot 3: queried `strategy_kros` for `name`/`target_date`/
     * `status` columns — `strategy_kros` (Key Result Objectives, per
     * CLAUDE.md's own Strategy model table: "linking objectives to KPIs")
     * is a pure numeric target/baseline/current tracker with no name or
     * date of its own at all (confirmed via `Schema::getColumnListing()` —
     * its real columns are `objective_id, kpi_id, target, baseline,
     * current, weight`), so this was a guaranteed "unknown column" SQL
     * error on every call, silently swallowed. The real dated, titled,
     * status-bearing entity for "plan milestones" is `StrategyObjective`
     * (table `strategy_objectives` — has `title`/`end_date`/`status`, and
     * is the actual OKR-tree node this feature was describing). Rewired
     * onto the real model rather than guessing new columns onto the wrong
     * one.
     */
    private function importStrategyMilestones(int $userId): int
    {
        if (! Schema::hasTable('strategy_objectives')) {
            return 0;
        }

        $calendar = $this->getOrCreateModuleCalendar($userId, 'Jalons Stratégiques', '#10B981', 'strategy_milestones');
        $synced   = 0;

        $objectives = DB::table('strategy_objectives')
            ->whereNotNull('end_date')
            ->where('status', '!=', 'completed')
            ->select('id', 'title', 'end_date', 'status')
            ->get();

        foreach ($objectives as $objective) {
            $date = Carbon::parse($objective->end_date);

            CalendarEvent::updateOrCreate(
                ['module_type' => 'StrategyObjective', 'module_id' => $objective->id, 'calendar_id' => $calendar->id],
                [
                    'title'      => "Jalon: {$objective->title}",
                    'start_at'   => $date->startOfDay(),
                    'end_at'     => $date->endOfDay(),
                    'all_day'    => true,
                    'source'     => 'module',
                    'color'      => '#10B981',
                    'status'     => 'confirmed',
                    'created_by' => $userId,
                ],
            );

            $synced++;
        }

        return $synced;
    }

    // -----------------------------------------------------------------------
    // Manufacturing: production orders
    // -----------------------------------------------------------------------

    /**
     * Chantier 19 Lot 3: queried `manufacturing_orders`, a table that has
     * never existed (real leftover table: `mfg_production_orders`) —
     * confirmed, but deliberately NOT fixed to point at it. Manufacturing
     * is explicitly excluded from Life MDG's 27-module scope (see
     * CLAUDE.md's "Scope: 27 modules" section); `mfg_production_orders` is
     * itself only a dead-but-kept leftover table with no real writer in
     * this app, read solely by Strategy's KPIRegistryService for a TRS/
     * defect-rate ratio — already flagged in CLAUDE.md's Chantier 9 entry
     * as an unresolved "Manufacturing-only ratios were dropped" doc/code
     * discrepancy, deliberately left rather than silently fixed. Wiring
     * this reader up to that same phantom table would mean building new
     * Calendar integration for a module this app doesn't ship, matching
     * neither of this session's "fix a real live bug" or "wire up a real
     * unrouted feature" precedents — left silently degrading (returns 0)
     * exactly as it already does, same as every module this app excludes.
     */
    private function importManufacturingOrders(int $userId): int
    {
        if (! Schema::hasTable('manufacturing_orders')) {
            return 0;
        }

        $calendar = $this->getOrCreateModuleCalendar($userId, 'Ordres de Fabrication', '#F59E0B', 'manufacturing');
        $synced   = 0;

        $orders = DB::table('manufacturing_orders')
            ->whereNotNull('scheduled_date')
            ->whereIn('status', ['planned', 'in_progress'])
            ->select('id', 'reference', 'scheduled_date', 'status')
            ->get();

        foreach ($orders as $order) {
            $date = Carbon::parse($order->scheduled_date);

            CalendarEvent::updateOrCreate(
                ['module_type' => 'ManufacturingOrder', 'module_id' => $order->id, 'calendar_id' => $calendar->id],
                [
                    'title'      => "OF: {$order->reference}",
                    'start_at'   => $date->copy()->startOfDay(),
                    'end_at'     => $date->copy()->endOfDay(),
                    'all_day'    => true,
                    'source'     => 'module',
                    'color'      => '#F59E0B',
                    'status'     => 'confirmed',
                    'created_by' => $userId,
                ],
            );

            $synced++;
        }

        return $synced;
    }

    // -----------------------------------------------------------------------
    // Accounting: payment dues, tax deadlines
    // -----------------------------------------------------------------------

    /**
     * Chantier 19 Lot 3: queried `accounting_invoices`, a table that has
     * never existed (real table: `acc_invoices` — the identical wrong-name
     * mistake already documented and fixed once in this exact spot for
     * Setup's `ImportDataJob::entityToTable()`, apparently made
     * independently a second time here) with a `total_amount` column that
     * doesn't exist either (real: `total`). Silently 0 events on every
     * call, confirmed via `Schema::hasTable()`/`getColumnListing()`.
     */
    private function importAccountingDeadlines(int $userId): int
    {
        if (! Schema::hasTable('acc_invoices')) {
            return 0;
        }

        $calendar = $this->getOrCreateModuleCalendar($userId, 'Échéances Comptables', '#6366F1', 'accounting');
        $synced   = 0;

        // Overdue/upcoming invoices
        $invoices = DB::table('acc_invoices')
            ->whereNotNull('due_date')
            ->whereIn('status', ['sent', 'partial'])
            ->where('due_date', '<=', now()->addDays(30))
            ->select('id', 'number', 'due_date', 'total', 'currency', 'status')
            ->get();

        foreach ($invoices as $invoice) {
            $due = Carbon::parse($invoice->due_date);

            CalendarEvent::updateOrCreate(
                ['module_type' => 'Invoice', 'module_id' => $invoice->id, 'calendar_id' => $calendar->id],
                [
                    'title'      => "Échéance: Facture #{$invoice->number}",
                    'start_at'   => $due->startOfDay(),
                    'end_at'     => $due->endOfDay(),
                    'all_day'    => true,
                    'source'     => 'module',
                    'color'      => '#6366F1',
                    'status'     => 'confirmed',
                    'created_by' => $userId,
                ],
            );

            $synced++;
        }

        return $synced;
    }

    // -----------------------------------------------------------------------
    // Workflow: scheduled automations
    // -----------------------------------------------------------------------

    private function importWorkflowSchedules(int $userId): int
    {
        if (! Schema::hasTable('workflow_executions')) {
            return 0;
        }

        $calendar = $this->getOrCreateModuleCalendar($userId, 'Workflows Planifiés', '#EC4899', 'workflow');
        $synced   = 0;

        $executions = DB::table('workflow_executions')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '>=', now())
            ->where('status', 'pending')
            ->select('id', 'workflow_id', 'scheduled_at')
            ->limit(50)
            ->get();

        foreach ($executions as $exec) {
            $scheduledAt = Carbon::parse($exec->scheduled_at);

            CalendarEvent::updateOrCreate(
                ['module_type' => 'WorkflowExecution', 'module_id' => $exec->id, 'calendar_id' => $calendar->id],
                [
                    'title'      => "Workflow #{$exec->workflow_id}",
                    'start_at'   => $scheduledAt,
                    'end_at'     => $scheduledAt->copy()->addMinutes(5),
                    'all_day'    => false,
                    'source'     => 'module',
                    'color'      => '#EC4899',
                    'status'     => 'tentative',
                    'created_by' => $userId,
                ],
            );

            $synced++;
        }

        return $synced;
    }

    // -----------------------------------------------------------------------
    // Timesheets: time entries as calendar blocks (read-only, scheduling view)
    // -----------------------------------------------------------------------

    /**
     * Imports timesheet entries for the given user as read-only calendar blocks.
     *
     * Two tables are checked (both Timesheets module variants):
     *  - timesheet_entries  (TimesheetEntry model — entry_date + hours_worked)
     *  - timesheets_entries (TimeEntry model      — work_date + hours)
     *
     * The employee → user relationship is resolved via hr_employees.user_id.
     */
    private function importTimesheetEntries(int $userId): int
    {
        // Resolve employee ID for this user
        if (! Schema::hasTable('hr_employees')) {
            return 0;
        }

        $employee = DB::table('hr_employees')
            ->where('user_id', $userId)
            ->select('id')
            ->first();

        if (! $employee) {
            return 0;
        }

        $calendar = $this->getOrCreateModuleCalendar($userId, 'Feuilles de temps', '#14B8A6', 'timesheets');
        $synced   = 0;

        // --- timesheet_entries (TimesheetEntry model: entry_date + hours_worked) ---
        if (Schema::hasTable('timesheet_entries')) {
            $entries = DB::table('timesheet_entries')
                ->where('employee_id', $employee->id)
                ->whereNotNull('entry_date')
                ->whereIn('status', ['submitted', 'approved'])
                ->where('entry_date', '>=', now()->subDays(30))
                ->select('id', 'entry_date', 'hours_worked', 'description', 'project_id', 'task_id')
                ->get();

            foreach ($entries as $entry) {
                $date  = Carbon::parse($entry->entry_date);
                $hours = (float) ($entry->hours_worked ?? 0);
                $label = $entry->description ?: 'Entrée de temps';

                CalendarEvent::updateOrCreate(
                    ['module_type' => 'TimesheetEntry', 'module_id' => $entry->id, 'calendar_id' => $calendar->id],
                    [
                        'title'      => "⏱ {$label} ({$hours}h)",
                        'start_at'   => $date->copy()->setHour(9)->setMinute(0),
                        'end_at'     => $date->copy()->setHour(9)->setMinute(0)->addMinutes((int) ($hours * 60)),
                        'all_day'    => false,
                        'source'     => 'module',
                        'color'      => '#14B8A6',
                        'status'     => 'confirmed',
                        'created_by' => $userId,
                    ],
                );

                $synced++;
            }
        }

        // --- timesheets_entries (TimeEntry model: work_date + hours) ---
        if (Schema::hasTable('timesheets_entries')) {
            $entries = DB::table('timesheets_entries')
                ->where('employee_id', $employee->id)
                ->whereNotNull('work_date')
                ->whereIn('status', ['submitted', 'approved'])
                ->where('work_date', '>=', now()->subDays(30))
                ->select('id', 'work_date', 'hours', 'task_description', 'project_id')
                ->get();

            foreach ($entries as $entry) {
                $date  = Carbon::parse($entry->work_date);
                $hours = (float) ($entry->hours ?? 0);
                $label = $entry->task_description ?: 'Entrée de temps';

                CalendarEvent::updateOrCreate(
                    ['module_type' => 'TimeEntry', 'module_id' => $entry->id, 'calendar_id' => $calendar->id],
                    [
                        'title'      => "⏱ {$label} ({$hours}h)",
                        'start_at'   => $date->copy()->setHour(9)->setMinute(0),
                        'end_at'     => $date->copy()->setHour(9)->setMinute(0)->addMinutes((int) ($hours * 60)),
                        'all_day'    => false,
                        'source'     => 'module',
                        'color'      => '#14B8A6',
                        'status'     => 'confirmed',
                        'created_by' => $userId,
                    ],
                );

                $synced++;
            }
        }

        return $synced;
    }

    // -----------------------------------------------------------------------
    // Helper
    // -----------------------------------------------------------------------

    private function getOrCreateModuleCalendar(
        int $userId,
        string $name,
        string $color,
        string $externalId,
    ): Calendar {
        return Calendar::firstOrCreate(
            ['user_id' => $userId, 'type' => 'module', 'external_calendar_id' => $externalId],
            [
                'name'       => $name,
                'color'      => $color,
                'source'     => 'local',
                'is_primary' => false,
                'is_visible' => true,
            ],
        );
    }
}
