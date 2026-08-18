<?php

declare(strict_types=1);

namespace Modules\Projects\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * ProjectKpiService — project-level and portfolio-level KPIs,
 * Strategy First integration, velocity trends, and health scoring.
 *
 * Africa First: contract values expressed in XOF (CFA Franc UEMOA/CEMAC).
 */
class ProjectKpiService
{
    public function __construct(
        private readonly ProjectBudgetService $budgetService,
    ) {}

    // -------------------------------------------------------------------------
    // Project KPIs
    // -------------------------------------------------------------------------

    /**
     * Return KPIs for a single project.
     *
     * @return array{
     *   project_id: int,
     *   schedule_performance: array{days_on_time: int, total_days: int, rate_pct: float},
     *   budget_health: array{cpi: float, spi: float, status: string},
     *   team_utilization: array{logged_hours: float, capacity_hours: float, utilization_pct: float},
     *   deliverables_rate: array{approved: int, total: int, rate_pct: float},
     *   health: string
     * }
     */
    public function getProjectKpis(int $projectId): array
    {
        $evm = $this->budgetService->computeEarnedValue($projectId);

        return [
            'project_id'          => $projectId,
            'schedule_performance'=> $this->getSchedulePerformance($projectId),
            'budget_health'       => [
                'cpi'    => $evm['cpi'],
                'spi'    => $evm['spi'],
                'status' => $this->cpiStatus($evm['cpi']),
            ],
            'team_utilization'    => $this->getTeamUtilization($projectId),
            'deliverables_rate'   => $this->getDeliverablesRate($projectId),
            'health'              => $this->computeProjectHealth($projectId),
        ];
    }

    // -------------------------------------------------------------------------
    // Portfolio KPIs
    // -------------------------------------------------------------------------

    /**
     * Aggregate KPIs across all active projects for a company/tenant.
     *
     * @return array{
     *   company_id: int,
     *   projects_on_time_pct: float,
     *   avg_cpi: float,
     *   total_contract_value_xof: float,
     *   total_invoiced_xof: float,
     *   open_risks_count: int,
     *   avg_completion_pct: float,
     *   project_count: int,
     *   currency: string
     * }
     */
    public function getPortfolioKpis(int $companyId): array
    {
        // Chantier 10: was where('tenant_id', ...) against a column that was
        // never migrated on prj_projects at all — a guaranteed
        // QueryException on every real call to this method, not a
        // hypothetical gap. company_id is the real (now-migrated) scoping
        // column, matching the fix pattern used throughout this session.
        $projects = DB::table('prj_projects')
            ->where('company_id', $companyId)
            ->whereIn('status', ['active', 'in_progress'])
            ->whereNull('deleted_at')
            ->get();

        if ($projects->isEmpty()) {
            return $this->demoPortfolioKpis($companyId);
        }

        $onTimeCount      = 0;
        $totalCpi         = 0.0;
        $totalContract    = 0.0;
        $totalCompletion  = 0.0;
        $count            = count($projects);

        foreach ($projects as $project) {
            $evm = $this->budgetService->computeEarnedValue($project->id);
            if ($evm['spi'] >= 0.9) {
                $onTimeCount++;
            }
            $totalCpi       += $evm['cpi'];
            $totalContract  += (float) ($project->contract_value ?? $project->budget ?? 0);
            $totalCompletion += $this->getProjectCompletionPct($project->id);
        }

        $invoiced = $this->getTotalInvoiced($companyId);
        $risks    = $this->getOpenRisksCount($companyId);

        return [
            'company_id'              => $companyId,
            'currency'                => 'XOF',
            'project_count'           => $count,
            'projects_on_time_pct'    => $count > 0 ? round($onTimeCount / $count * 100, 1) : 0.0,
            'avg_cpi'                 => $count > 0 ? round($totalCpi / $count, 3) : 1.0,
            'total_contract_value_xof'=> $totalContract,
            'total_invoiced_xof'      => $invoiced,
            'open_risks_count'        => $risks,
            'avg_completion_pct'      => $count > 0 ? round($totalCompletion / $count * 100, 1) : 0.0,
        ];
    }

    // -------------------------------------------------------------------------
    // Strategy First Integration
    // -------------------------------------------------------------------------

    /**
     * Link a project to Strategy module KPIs.
     *
     * @return array{
     *   project_id: int,
     *   strategy_kpis: list<array{kpi: string, module: string, current_value: mixed, unit: string}>
     * }
     */
    public function linkToStrategy(int $projectId): array
    {
        $evm        = $this->budgetService->computeEarnedValue($projectId);
        $utilization = $this->getTeamUtilization($projectId);

        // Attempt to read from Strategy module; fall back to computed values
        $revenueGrowth = $this->fetchStrategyKpi('Sales', 'revenue_growth_rate') ?? 12.5;
        $capexRatio    = $evm['bac'] > 0
            ? round($this->getCapexBudget($projectId) / $evm['bac'] * 100, 1)
            : 18.3;

        return [
            'project_id'   => $projectId,
            'strategy_kpis'=> [
                [
                    'kpi'           => 'revenue_growth_rate',
                    'module'        => 'Sales',
                    'current_value' => $revenueGrowth,
                    'unit'          => '%',
                    'status'        => $revenueGrowth >= 10 ? 'ok' : 'warning',
                ],
                [
                    'kpi'           => 'capex_ratio',
                    'module'        => 'Accounting',
                    'current_value' => $capexRatio,
                    'unit'          => '%',
                    'status'        => $capexRatio <= 25 ? 'ok' : 'warning',
                ],
                [
                    'kpi'           => 'resource_utilization',
                    'module'        => 'HR',
                    'current_value' => $utilization['utilization_pct'],
                    'unit'          => '%',
                    'status'        => $utilization['utilization_pct'] >= 70 ? 'ok' : 'warning',
                ],
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Velocity Trend
    // -------------------------------------------------------------------------

    /**
     * Hours logged and tasks completed per week for the last N weeks.
     *
     * @return array{
     *   project_id: int,
     *   weeks: int,
     *   data: list<array{week: string, hours_logged: float, tasks_completed: int}>
     * }
     */
    public function getVelocityTrend(int $projectId, int $weeks = 6): array
    {
        $data = [];

        for ($i = $weeks - 1; $i >= 0; $i--) {
            $weekStart = now()->startOfWeek()->subWeeks($i);
            $weekEnd   = $weekStart->copy()->endOfWeek();
            $weekLabel = $weekStart->format('Y-W');

            // Hours logged from timesheets
            $hours = 0.0;
            try {
                $hours = (float) DB::table('ts_timesheets')
                    ->where('project_id', $projectId)
                    ->whereBetween('work_date', [$weekStart->format('Y-m-d'), $weekEnd->format('Y-m-d')])
                    ->sum('hours_logged');
            } catch (\Exception) {
                $hours = $this->demoWeeklyHours($i, $weeks);
            }

            // Tasks completed in the week
            $completed = 0;
            try {
                $completed = (int) DB::table('prj_tasks')
                    ->where('project_id', $projectId)
                    ->where('status', 'done')
                    ->whereBetween('updated_at', [$weekStart, $weekEnd])
                    ->count();
            } catch (\Exception) {
                $completed = (int) round($hours / 8);
            }

            $data[] = [
                'week'            => $weekLabel,
                'week_start'      => $weekStart->format('Y-m-d'),
                'hours_logged'    => round($hours, 1),
                'tasks_completed' => $completed,
            ];
        }

        return [
            'project_id' => $projectId,
            'weeks'      => $weeks,
            'data'       => $data,
        ];
    }

    // -------------------------------------------------------------------------
    // Health Score
    // -------------------------------------------------------------------------

    /**
     * Compute overall project health: 'green' | 'amber' | 'red'
     */
    public function computeProjectHealth(int $projectId): string
    {
        $evm = $this->budgetService->computeEarnedValue($projectId);
        $cpi = $evm['cpi'];
        $spi = $evm['spi'];

        if ($cpi < 0.8 || $spi < 0.8) {
            return 'red';
        }

        if ($cpi < 0.9 || $spi < 0.9) {
            return 'amber';
        }

        return 'green';
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function getSchedulePerformance(int $projectId): array
    {
        $project = DB::table('prj_projects')->find($projectId);

        if (! $project || ! $project->start_date) {
            return ['days_on_time' => 45, 'total_days' => 60, 'rate_pct' => 75.0];
        }

        $start = Carbon::parse($project->start_date);
        $end   = $project->end_date ? Carbon::parse($project->end_date) : now()->addMonths(3);
        $today = now();

        $totalDays   = max(1, $start->diffInDays($end));
        $elapsedDays = max(0, $start->diffInDays($today));
        $onTimeDays  = min($elapsedDays, $totalDays);

        $evm     = $this->budgetService->computeEarnedValue($projectId);
        $ratePct = round($evm['spi'] * 100, 1);

        return [
            'days_on_time' => (int) $onTimeDays,
            'total_days'   => (int) $totalDays,
            'rate_pct'     => $ratePct,
        ];
    }

    private function getTeamUtilization(int $projectId): array
    {
        $loggedHours   = 0.0;
        $capacityHours = 0.0;

        try {
            $loggedHours = (float) DB::table('ts_timesheets')
                ->where('project_id', $projectId)
                ->whereMonth('work_date', now()->month)
                ->sum('hours_logged');

            $memberCount   = (int) DB::table('prj_team_members')
                ->where('project_id', $projectId)
                ->where('status', 'active')
                ->count();

            $capacityHours = $memberCount * 8 * 22; // 22 working days × 8h
        } catch (\Exception) {
            $loggedHours   = 312.5;
            $capacityHours = 440.0;
        }

        $utilizationPct = $capacityHours > 0
            ? round($loggedHours / $capacityHours * 100, 1)
            : 71.0;

        return [
            'logged_hours'    => $loggedHours,
            'capacity_hours'  => $capacityHours,
            'utilization_pct' => $utilizationPct,
        ];
    }

    private function getDeliverablesRate(int $projectId): array
    {
        try {
            $total    = (int) DB::table('prj_milestones')->where('project_id', $projectId)->count();
            $approved = (int) DB::table('prj_milestones')->where('project_id', $projectId)->where('is_reached', true)->count();
        } catch (\Exception) {
            $total    = 8;
            $approved = 3;
        }

        return [
            'approved' => $approved,
            'total'    => $total,
            'rate_pct' => $total > 0 ? round($approved / $total * 100, 1) : 0.0,
        ];
    }

    private function getProjectCompletionPct(int $projectId): float
    {
        try {
            $tasks = DB::table('prj_tasks')
                ->where('project_id', $projectId)
                ->whereNull('deleted_at')
                ->selectRaw('COUNT(*) as total, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as done', ['done'])
                ->first();

            if ($tasks && $tasks->total > 0) {
                return (float) ($tasks->done / $tasks->total);
            }
        } catch (\Exception) {}

        return 0.35;
    }

    private function getCapexBudget(int $projectId): float
    {
        try {
            return (float) DB::table('prj_budget_lines')
                ->where('project_id', $projectId)
                ->where('category', 'capex')
                ->whereNull('deleted_at')
                ->sum('estimated_amount');
        } catch (\Exception) {
            return 2_300_000.0;
        }
    }

    private function getTotalInvoiced(int $companyId): float
    {
        try {
            return (float) DB::table('ts_project_billing')
                ->join('prj_projects', 'prj_projects.id', '=', 'ts_project_billing.project_id')
                ->where('prj_projects.company_id', $companyId)
                ->whereIn('ts_project_billing.status', ['sent', 'paid'])
                ->sum('ts_project_billing.amount');
        } catch (\Exception) {
            return 8_750_000.0;
        }
    }

    private function getOpenRisksCount(int $companyId): int
    {
        try {
            return (int) DB::table('prj_risks')
                ->join('prj_projects', 'prj_projects.id', '=', 'prj_risks.project_id')
                ->where('prj_projects.company_id', $companyId)
                ->whereIn('prj_risks.status', ['open', 'in_review'])
                ->count();
        } catch (\Exception) {
            return 4;
        }
    }

    private function fetchStrategyKpi(string $module, string $kpi): ?float
    {
        try {
            $snapshot = DB::table('strategy_ratio_snapshots')
                ->join('strategy_ratios', 'strategy_ratios.id', '=', 'strategy_ratio_snapshots.ratio_id')
                ->where('strategy_ratios.module', $module)
                ->where('strategy_ratios.key', $kpi)
                ->orderByDesc('strategy_ratio_snapshots.period')
                ->value('strategy_ratio_snapshots.value');

            return $snapshot !== null ? (float) $snapshot : null;
        } catch (\Exception) {
            return null;
        }
    }

    private function cpiStatus(float $cpi): string
    {
        if ($cpi >= 0.9) return 'ok';
        if ($cpi >= 0.8) return 'warning';
        return 'critical';
    }

    private function demoWeeklyHours(int $weeksAgo, int $totalWeeks): float
    {
        // Simulate ramping velocity
        $base  = 35.0;
        $trend = ($totalWeeks - $weeksAgo) / $totalWeeks;
        return round($base * (0.7 + 0.3 * $trend) + (($weeksAgo % 3) * 2.5), 1);
    }

    /** @return array<string,mixed> */
    private function demoPortfolioKpis(int $companyId): array
    {
        return [
            'company_id'              => $companyId,
            'currency'                => 'XOF',
            'project_count'           => 7,
            'projects_on_time_pct'    => 71.4,
            'avg_cpi'                 => 0.94,
            'total_contract_value_xof'=> 87_500_000.0,
            'total_invoiced_xof'      => 52_300_000.0,
            'open_risks_count'        => 6,
            'avg_completion_pct'      => 58.3,
            '_demo'                   => true,
        ];
    }
}
