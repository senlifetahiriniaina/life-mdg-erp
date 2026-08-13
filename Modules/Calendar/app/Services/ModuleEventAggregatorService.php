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

    private function importHrLeaves(int $userId): int
    {
        if (! Schema::hasTable('hr_leaves')) {
            return 0;
        }

        $calendar = $this->getOrCreateModuleCalendar($userId, 'HR Congés', '#8B5CF6', 'hr_leaves');
        $synced   = 0;

        $leaves = DB::table('hr_leaves')
            ->join('hr_employees', 'hr_leaves.employee_id', '=', 'hr_employees.id')
            ->where('hr_employees.user_id', $userId)
            ->where('hr_leaves.status', 'approved')
            ->whereNotNull('hr_leaves.start_date')
            ->select('hr_leaves.*', 'hr_employees.first_name', 'hr_employees.last_name')
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

    private function importProjectTasks(int $userId): int
    {
        if (! Schema::hasTable('project_tasks')) {
            return 0;
        }

        $calendar = $this->getOrCreateModuleCalendar($userId, 'Tâches Projets', '#0EA5E9', 'project_tasks');
        $synced   = 0;

        $tasks = DB::table('project_tasks')
            ->where('assigned_to', $userId)
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

    private function importStrategyMilestones(int $userId): int
    {
        if (! Schema::hasTable('strategy_kros')) {
            return 0;
        }

        $calendar = $this->getOrCreateModuleCalendar($userId, 'Jalons Stratégiques', '#10B981', 'strategy_milestones');
        $synced   = 0;

        $kros = DB::table('strategy_kros')
            ->whereNotNull('target_date')
            ->where('status', '!=', 'completed')
            ->select('id', 'name', 'target_date', 'status')
            ->get();

        foreach ($kros as $kro) {
            $date = Carbon::parse($kro->target_date);

            CalendarEvent::updateOrCreate(
                ['module_type' => 'KRO', 'module_id' => $kro->id, 'calendar_id' => $calendar->id],
                [
                    'title'      => "Jalon: {$kro->name}",
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

    private function importAccountingDeadlines(int $userId): int
    {
        if (! Schema::hasTable('accounting_invoices')) {
            return 0;
        }

        $calendar = $this->getOrCreateModuleCalendar($userId, 'Échéances Comptables', '#6366F1', 'accounting');
        $synced   = 0;

        // Overdue/upcoming invoices
        $invoices = DB::table('accounting_invoices')
            ->whereNotNull('due_date')
            ->whereIn('status', ['sent', 'partial'])
            ->where('due_date', '<=', now()->addDays(30))
            ->select('id', 'number', 'due_date', 'total_amount', 'currency', 'status')
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
