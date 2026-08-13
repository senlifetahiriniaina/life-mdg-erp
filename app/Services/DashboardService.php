<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /**
     * Return aggregated dashboard metrics for the user based on their role.
     *
     * Every DB query is wrapped in try/catch so missing tables (modules not yet
     * migrated) silently return 0 instead of crashing.
     *
     * @return array<string, mixed>
     */
    public function getMetricsForRole(User $user): array
    {
        $role = $user->roles->first()?->name ?? 'employee';

        return match (true) {
            in_array($role, ['super-admin', 'admin'], true) => $this->adminMetrics(),
            $role === 'manager'    => $this->managerMetrics($user),
            $role === 'accountant' => $this->accountantMetrics(),
            $role === 'hr-manager' => $this->hrManagerMetrics(),
            $role === 'sales-rep'  => $this->salesRepMetrics($user),
            default                => $this->employeeMetrics($user),
        };
    }

    // ─── Admin / Super-admin ─────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function adminMetrics(): array
    {
        return [
            'role'               => 'admin',
            'users_count'        => $this->safe(fn () => DB::table('users')->whereNull('deleted_at')->count()),
            'modules_enabled'    => $this->safe(fn () => DB::table('tenant_modules')->where('enabled', true)->count()),
            'total_revenue'      => $this->safe(fn () => (float) DB::table('acc_invoices')->where('status', 'paid')->sum('total')),
            'open_tickets'       => $this->safe(fn () => DB::table('hd_tickets')->whereNotIn('status', ['closed', 'resolved'])->count()),
            'pending_approvals'  => $this->pendingApprovalsCount(),
            'active_users_today' => $this->safe(fn () => DB::table('users')->whereDate('last_login_at', today())->count()),
            'mrr'                => $this->safe(fn () => (float) DB::table('acc_invoices')->where('status', 'paid')->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->sum('total')),
            'db_latency_ms'      => $this->dbLatency(),
        ];
    }

    // ─── Manager ────────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function managerMetrics(User $user): array
    {
        return [
            'role'                 => 'manager',
            'team_size'            => $this->safe(fn () => DB::table('hr_employees')->where('status', 'active')->count()),
            'pending_leave_requests' => $this->safe(fn () => DB::table('hr_leave_requests')->where('status', 'pending')->count()),
            'open_tasks'           => $this->safe(fn () => DB::table('prj_tasks')->whereNotIn('status', ['done', 'cancelled'])->count()),
            'overdue_tasks'        => $this->safe(fn () => DB::table('prj_tasks')->where('due_date', '<', now())->whereNotIn('status', ['done', 'cancelled'])->count()),
            'budget_consumed'      => $this->safe(fn () => (float) DB::table('acc_expense_reports')->whereIn('status', ['approved', 'paid'])->sum('total_amount')),
            'team_performance_avg' => $this->safe(fn () => (float) (DB::table('hr_performance_appraisals')->avg('overall_score') ?? 0)),
        ];
    }

    // ─── Accountant ──────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function accountantMetrics(): array
    {
        return [
            'role'                        => 'accountant',
            'unpaid_invoices_count'        => $this->safe(fn () => DB::table('acc_invoices')->where('status', 'unpaid')->count()),
            'unpaid_invoices_total'        => $this->safe(fn () => (float) DB::table('acc_invoices')->where('status', 'unpaid')->sum('total')),
            'expense_reports_pending'      => $this->safe(fn () => DB::table('acc_expense_reports')->where('status', 'submitted')->count()),
            'bank_reconciliation_pending'  => $this->safe(fn () => DB::table('acc_bank_feed_transactions')->where('status', 'pending')->count()),
            'vat_due_amount'               => $this->safe(fn () => (float) DB::table('acc_vat_declarations')->where('status', 'draft')->sum('vat_due')),
            'overdue_invoices'             => $this->safe(fn () => DB::table('acc_invoices')->where('status', 'unpaid')->where('due_date', '<', now())->count()),
        ];
    }

    // ─── HR Manager ─────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function hrManagerMetrics(): array
    {
        return [
            'role'             => 'hr-manager',
            'headcount'        => $this->safe(fn () => DB::table('hr_employees')->where('status', 'active')->count()),
            'open_positions'   => $this->safe(fn () => DB::table('hr_job_postings')->where('status', 'open')->count()),
            'pending_leaves'   => $this->safe(fn () => DB::table('hr_leave_requests')->where('status', 'pending')->count()),
            'upcoming_reviews' => $this->safe(fn () => DB::table('hr_performance_cycles')->where('status', 'active')->count()),
            'turnover_rate'    => 4.2,  // mock — real calculation requires historical data
            'avg_salary'       => 42000.0, // mock — sensitive aggregation
        ];
    }

    // ─── Sales Rep ───────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function salesRepMetrics(User $user): array
    {
        return [
            'role'                  => 'sales-rep',
            'my_leads_count'        => $this->safe(fn () => DB::table('crm_leads')->where('owner_id', $user->id)->whereNotIn('status', ['converted', 'lost'])->count()),
            'my_opportunities_count' => $this->safe(fn () => DB::table('crm_opportunities')->where('owner_id', $user->id)->whereNotIn('stage', ['won', 'lost'])->count()),
            'my_pipeline_value'     => $this->safe(fn () => (float) DB::table('crm_opportunities')->where('owner_id', $user->id)->whereNotIn('stage', ['won', 'lost'])->sum('amount')),
            'quota_progress'        => 62.0, // mock — real quota system would need a quota table
            'open_activities'       => $this->safe(fn () => DB::table('crm_activities')->where('owner_id', $user->id)->where('status', 'pending')->count()),
            'won_deals_this_month'  => $this->safe(fn () => DB::table('crm_opportunities')->where('owner_id', $user->id)->where('stage', 'won')->whereMonth('updated_at', now()->month)->whereYear('updated_at', now()->year)->count()),
        ];
    }

    // ─── Employee ────────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function employeeMetrics(User $user): array
    {
        return [
            'role'                => 'employee',
            'my_open_tasks'       => $this->safe(fn () => DB::table('prj_tasks')->where('assignee_id', $user->id)->whereNotIn('status', ['done', 'cancelled'])->count()),
            'my_leave_balance'    => $this->safe(fn () => (float) DB::table('hr_leave_requests')->where('user_id', $user->id)->where('status', 'approved')->whereYear('created_at', now()->year)->sum('days_requested')),
            'my_pending_expenses' => $this->safe(fn () => DB::table('acc_expense_reports')->where('submitted_by', $user->id)->where('status', 'submitted')->count()),
            'my_open_tickets'     => $this->safe(fn () => DB::table('hd_tickets')->where('reporter_id', $user->id)->whereNotIn('status', ['closed', 'resolved'])->count()),
            'upcoming_events'     => 3, // mock
        ];
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function pendingApprovalsCount(): int
    {
        $expense = $this->safe(fn () => DB::table('acc_expense_reports')->where('status', 'submitted')->count());
        $leaves  = $this->safe(fn () => DB::table('hr_leave_requests')->where('status', 'pending')->count());
        $docs    = $this->safe(fn () => DB::table('doc_approval_instances')->where('status', 'pending')->count());

        return $expense + $leaves + $docs;
    }

    private function dbLatency(): float
    {
        $start = microtime(true);
        try {
            DB::select('SELECT 1');
        } catch (\Throwable) {
            return -1.0;
        }

        return round((microtime(true) - $start) * 1000, 2);
    }

    /**
     * Execute a callable and return its result, or 0 on any exception.
     *
     * @template T
     * @param  callable(): T  $callable
     * @return T|int
     */
    private function safe(callable $callable): mixed
    {
        try {
            return $callable();
        } catch (\Throwable) {
            return 0;
        }
    }
}
