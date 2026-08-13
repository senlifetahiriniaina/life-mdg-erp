<?php

declare(strict_types=1);

namespace Modules\Accounting\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Modules\Accounting\Models\Budget;
use Modules\Accounting\Models\BudgetActual;
use Modules\Accounting\Models\GLEntry;
use Modules\Accounting\Models\BudgetAlert;
use Modules\Accounting\Models\BudgetForecast;
use Modules\Accounting\Models\BudgetLine;

class BudgetVarianceService
{
    public function calculateVariance(Budget $budget): array
    {
        $lines = $budget->budgetLines()->get();
        $totalBudget = $lines->sum(fn($l) => (float) ($l->budgeted_amount ?? $l->budget_amount ?? 0));

        // Compute actual from GL entries linked to budget lines (or fall back to BudgetActual)
        $glAccountIds = $lines->pluck('gl_account_id')->filter()->unique()->values()->all();
        $totalActual = count($glAccountIds) > 0
            ? (float) GLEntry::whereIn('gl_account_id', $glAccountIds)->sum('debit_amount')
            : $this->getTotalActual($budget);

        $variance = $totalBudget - $totalActual;
        $variancePercent = $totalBudget > 0 ? ($variance / $totalBudget) * 100 : 0;

        return [
            'budget_amount' => $totalBudget,
            'budgeted_amount' => $totalBudget,
            'actual_amount' => $totalActual,
            'variance_amount' => round($variance, 2),
            'variance_percent' => round($variancePercent, 2),
            'remaining_budget' => round($variance, 2),
            'is_over_budget' => $variance < 0,
            'budget_utilization_percent' => $totalBudget > 0 ? round(($totalActual / $totalBudget) * 100, 2) : 0,
        ];
    }

    public function calculateLineVariance(BudgetLine $line): array
    {
        $budgetAmount = (float) $line->budget_amount;
        $actualAmount = (float) $line->budgetActuals()->sum('actual_amount');
        $variance = $actualAmount - $budgetAmount;
        $variancePercent = $budgetAmount > 0 ? ($variance / $budgetAmount) * 100 : 0;

        return [
            'account_id' => $line->gl_account_id,
            'account_name' => $line->glAccount?->name,
            'budget_amount' => $budgetAmount,
            'actual_amount' => $actualAmount,
            'variance_amount' => round($variance, 2),
            'variance_percent' => round($variancePercent, 2),
            'is_over_budget' => $variance > 0,
            'remaining_budget' => round($budgetAmount - $actualAmount, 2),
        ];
    }

    public function getOverBudgetLines(Budget $budget): Collection
    {
        $overBudgetLines = [];

        foreach ($budget->budgetLines as $line) {
            $variance = $this->calculateLineVariance($line);
            if ($variance['is_over_budget']) {
                $overBudgetLines[] = $variance;
            }
        }

        return collect($overBudgetLines);
    }

    public function triggerAlerts(Budget $budget): Collection
    {
        $alerts = [];
        $thresholds = [
            'over_budget' => 100,
            'approaching_limit' => 90,
            'variance' => 75,
        ];

        foreach ($budget->budgetLines as $line) {
            $variance = $this->calculateLineVariance($line);
            $utilizationPercent = $variance['variance_percent'];

            // Check for over-budget
            if ($utilizationPercent >= $thresholds['over_budget']) {
                $alert = BudgetAlert::firstOrCreate(
                    [
                        'budget_id' => $budget->id,
                        'budget_line_id' => $line->id,
                        'alert_type' => 'over_budget',
                        'status' => 'triggered',
                    ],
                    [
                        'threshold_percent' => 100,
                        'current_variance_percent' => $utilizationPercent,
                        'triggered_at' => now(),
                    ]
                );
                $alerts[] = $alert;
            }
            // Check for approaching limit
            elseif ($utilizationPercent >= $thresholds['approaching_limit']) {
                $alert = BudgetAlert::firstOrCreate(
                    [
                        'budget_id' => $budget->id,
                        'budget_line_id' => $line->id,
                        'alert_type' => 'approaching_limit',
                        'status' => 'triggered',
                    ],
                    [
                        'threshold_percent' => 90,
                        'current_variance_percent' => $utilizationPercent,
                        'triggered_at' => now(),
                    ]
                );
                $alerts[] = $alert;
            }
            // Check for variance threshold
            elseif ($utilizationPercent >= $thresholds['variance']) {
                $alert = BudgetAlert::firstOrCreate(
                    [
                        'budget_id' => $budget->id,
                        'budget_line_id' => $line->id,
                        'alert_type' => 'variance',
                        'status' => 'triggered',
                    ],
                    [
                        'threshold_percent' => 75,
                        'current_variance_percent' => $utilizationPercent,
                        'triggered_at' => now(),
                    ]
                );
                $alerts[] = $alert;
            }
        }

        return collect($alerts);
    }

    public function forecastRemaining(BudgetLine $line): array
    {
        $budget = $line->budget;
        $today = Carbon::now();
        $daysInPeriod = $budget->budget_period_start->diffInDays($budget->budget_period_end);
        $daysElapsed = $budget->budget_period_start->diffInDays($today);
        $daysRemaining = $daysInPeriod - $daysElapsed;

        $totalBudget = (float) $line->budget_amount;
        $actualToDate = (float) $line->budgetActuals()->sum('actual_amount');
        $remainingBudget = $totalBudget - $actualToDate;

        // Linear forecast: extrapolate current spending rate
        $dailyRate = $daysElapsed > 0 ? $actualToDate / $daysElapsed : 0;
        $projectedRemaining = $dailyRate * $daysRemaining;
        $projectedTotal = $actualToDate + $projectedRemaining;

        // Calculate forecast variance
        $forecastVariance = $projectedTotal - $totalBudget;
        $forecastVariancePercent = $totalBudget > 0 ? ($forecastVariance / $totalBudget) * 100 : 0;

        return [
            'actual_to_date' => round($actualToDate, 2),
            'remaining_budget' => round($remainingBudget, 2),
            'daily_spending_rate' => round($dailyRate, 2),
            'days_remaining' => $daysRemaining,
            'projected_remaining_spend' => round($projectedRemaining, 2),
            'projected_total_spend' => round($projectedTotal, 2),
            'projected_variance' => round($forecastVariance, 2),
            'projected_variance_percent' => round($forecastVariancePercent, 2),
            'will_exceed_budget' => $forecastVariance > 0,
            'confidence_level' => $daysElapsed > 0 ? 0.85 : 0.50,
        ];
    }

    public function getVarianceTrend(Budget $budget, int $months = 12): array
    {
        $trends = [];
        $currentDate = Carbon::now();

        for ($i = 0; $i < $months; $i++) {
            $month = $currentDate->copy()->subMonths($i);
            $startDate = $month->copy()->startOfMonth();
            $endDate = $month->copy()->endOfMonth();

            $actualAmount = BudgetActual::where('budget_id', $budget->id)
                ->whereBetween('period_month', [$startDate, $endDate])
                ->sum('actual_amount');

            $budgetAmount = $budget->budgetLines()->sum('budget_amount') / 12; // Monthly average

            $variance = $actualAmount - $budgetAmount;
            $variancePercent = $budgetAmount > 0 ? ($variance / $budgetAmount) * 100 : 0;

            $trends[] = [
                'month' => $month->format('Y-m'),
                'budget_amount' => round($budgetAmount, 2),
                'actual_amount' => round($actualAmount, 2),
                'variance' => round($variance, 2),
                'variance_percent' => round($variancePercent, 2),
            ];
        }

        return array_reverse($trends);
    }

    public function createForecast(BudgetLine $line, int $periods = 6, string $method = 'linear'): Collection
    {
        $forecasts = [];
        $budget = $line->budget;
        $totalBudget = (float) $line->budget_amount;
        $monthlyBudget = $totalBudget / 12;

        for ($i = 1; $i <= $periods; $i++) {
            $forecastMonth = Carbon::now()->addMonths($i)->startOfMonth();
            $forecastAmount = $this->forecastAmount($line, $method, $i);

            $forecast = BudgetForecast::updateOrCreate(
                [
                    'budget_id' => $budget->id,
                    'budget_line_id' => $line->id,
                    'forecast_month' => $forecastMonth,
                ],
                [
                    'forecasted_amount' => $forecastAmount,
                    'forecast_method' => $method,
                    'confidence_level' => max(0.5, 1.0 - ($i * 0.05)),
                ]
            );

            $forecasts[] = $forecast;
        }

        return collect($forecasts);
    }

    public function getMonthlyComparison(Budget $budget, string $month): array
    {
        $monthDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth();

        $totalBudget = $budget->budgetLines()->sum('budget_amount');
        $monthlyBudget = $totalBudget / 12;

        $actualAmount = BudgetActual::where('budget_id', $budget->id)
            ->where('period_month', $monthDate)
            ->sum('actual_amount');

        $variance = $actualAmount - $monthlyBudget;
        $variancePercent = $monthlyBudget > 0 ? ($variance / $monthlyBudget) * 100 : 0;

        return [
            'month' => $month,
            'budget_amount' => round($monthlyBudget, 2),
            'actual_amount' => round($actualAmount, 2),
            'variance_amount' => round($variance, 2),
            'variance_percent' => round($variancePercent, 2),
            'utilization_percent' => round(($actualAmount / $monthlyBudget) * 100, 2),
        ];
    }

    private function getTotalActual(Budget $budget): float
    {
        return (float) $budget->budgetActuals()->sum('actual_amount');
    }

    private function forecastAmount(BudgetLine $line, string $method, int $period): float
    {
        $actuals = $line->budgetActuals()
            ->orderBy('period_month', 'asc')
            ->limit(12)
            ->pluck('actual_amount')
            ->map(fn ($v) => (float) $v)
            ->toArray();

        if (empty($actuals)) {
            return 0;
        }

        return match ($method) {
            'linear' => $this->linearForecast($actuals, $period),
            'exponential' => $this->exponentialForecast($actuals, $period),
            'seasonal' => $this->seasonalForecast($actuals, $period),
            default => end($actuals),
        };
    }

    private function linearForecast(array $actuals, int $period): float
    {
        if (count($actuals) < 2) {
            return end($actuals) ?? 0;
        }

        $n = count($actuals);
        $x = range(1, $n);
        $sumX = array_sum($x);
        $sumY = array_sum($actuals);
        $sumXY = array_sum(array_map(fn ($i, $v) => $i * $v, $x, $actuals));
        $sumX2 = array_sum(array_map(fn ($i) => $i * $i, $x));

        $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);
        $intercept = ($sumY - $slope * $sumX) / $n;

        return max(0, $intercept + $slope * ($n + $period));
    }

    private function exponentialForecast(array $actuals, int $period): float
    {
        if (empty($actuals)) {
            return 0;
        }

        $alpha = 0.3;
        $forecast = $actuals[0];

        foreach ($actuals as $actual) {
            $forecast = $alpha * $actual + (1 - $alpha) * $forecast;
        }

        return max(0, $forecast);
    }

    private function seasonalForecast(array $actuals, int $period): float
    {
        if (count($actuals) < 12) {
            return $this->linearForecast($actuals, $period);
        }

        // Simple seasonal: use same month from previous year
        $monthIndex = ($period - 1) % 12;
        return max(0, $actuals[$monthIndex] ?? end($actuals));
    }

    // ─── Additional methods required by tests ─────────────────────────────────

    /**
     * Returns a full variance report for a budget.
     */
    public function varianceReport(Budget $budget): array
    {
        $lines = $budget->lines()->with('account', 'glAccount')->get();

        $accounts = [];
        $ytdBudgeted = 0.0;
        $ytdActual = 0.0;
        $ytdVariance = 0.0;
        $categorySummaries = [];

        foreach ($lines as $line) {
            $budgeted = (float) ($line->budgeted_amount ?? $line->budget_amount ?? 0);
            $actual = (float) ($line->actual_amount ?? 0);
            $variance = (float) ($line->variance ?? ($actual - $budgeted));

            $ytdBudgeted += $budgeted;
            $ytdActual += $actual;
            $ytdVariance += $variance;

            $category = $line->category ?? 'other';
            if (!isset($categorySummaries[$category])) {
                $categorySummaries[$category] = ['budgeted' => 0.0, 'actual' => 0.0, 'variance' => 0.0];
            }
            $categorySummaries[$category]['budgeted'] += $budgeted;
            $categorySummaries[$category]['actual'] += $actual;
            $categorySummaries[$category]['variance'] += $variance;

            $accounts[] = [
                'line_id' => $line->id,
                'account_id' => $line->account_id ?? $line->gl_account_id,
                'period_month' => $line->period_month,
                'period_year' => $line->period_year,
                'budgeted_amount' => $budgeted,
                'actual_amount' => $actual,
                'variance' => round($variance, 2),
                'variance_pct' => $budgeted > 0 ? round($variance / $budgeted * 100, 2) : 0.0,
            ];
        }

        $ytdVariancePct = $ytdBudgeted > 0 ? round($ytdVariance / $ytdBudgeted * 100, 2) : 0.0;

        return [
            'budget_id' => $budget->id,
            'budget_name' => $budget->name,
            'fiscal_year' => $budget->fiscal_year,
            'accounts' => $accounts,
            'category_summaries' => $categorySummaries,
            'ytd' => [
                'budgeted' => round($ytdBudgeted, 2),
                'actual' => round($ytdActual, 2),
                'variance' => round($ytdVariance, 2),
                'variance_pct' => $ytdVariancePct,
            ],
        ];
    }

    /**
     * Returns monthly trend for 12 months.
     */
    public function monthlyTrend(Budget $budget): array
    {
        $year = $budget->fiscal_year ?? now()->year;
        $trend = [];
        $cumulative = 0.0;

        for ($month = 1; $month <= 12; $month++) {
            $lines = $budget->lines()->where('period_month', $month)->get();
            $budgeted = $lines->sum(fn ($l) => (float) ($l->budgeted_amount ?? $l->budget_amount ?? 0));
            $actual = $lines->sum(fn ($l) => (float) ($l->actual_amount ?? 0));
            $variance = $actual - $budgeted;
            $cumulative += $variance;

            $trend[] = [
                'month' => $month,
                'month_name' => \Carbon\Carbon::createFromDate($year, $month, 1)->format('F'),
                'year' => $year,
                'budgeted' => round($budgeted, 2),
                'actual' => round($actual, 2),
                'variance' => round($variance, 2),
                'cumulative_variance' => round($cumulative, 2),
            ];
        }

        return $trend;
    }

    /**
     * Returns the top N lines sorted by absolute variance (descending).
     */
    public function topVariances(Budget $budget, int $n = 5): array
    {
        $lines = $budget->lines()->with('account', 'glAccount')->get();

        $items = $lines->map(function ($line) {
            $budgeted = (float) ($line->budgeted_amount ?? $line->budget_amount ?? 0);
            $actual = (float) ($line->actual_amount ?? 0);
            $variance = (float) ($line->variance ?? ($actual - $budgeted));

            return [
                'line_id' => $line->id,
                'account_id' => $line->account_id ?? $line->gl_account_id,
                'budgeted_amount' => $budgeted,
                'actual_amount' => $actual,
                'variance' => $variance,
                'variance_pct' => $budgeted > 0 ? round($variance / $budgeted * 100, 2) : 0.0,
            ];
        })->sortByDesc(fn ($item) => abs($item['variance']))->values()->take($n)->toArray();

        return $items;
    }

    /**
     * Projects a budget scenario.
     */
    public function projectScenario(\Modules\Accounting\Models\BudgetScenario $scenario): array
    {
        $budget = $scenario->baseBudget;
        $lines = $budget->lines()->with('account', 'glAccount')->get();

        $projectedRevenue = 0.0;
        $projectedExpenses = 0.0;
        $projectedLines = [];

        foreach ($lines as $line) {
            $budgeted = (float) ($line->budgeted_amount ?? $line->budget_amount ?? 0);
            $type = optional($line->account)->type ?? optional($line->glAccount)->account_type ?? 'expense';

            if ($scenario->adjustment_type === 'percentage') {
                $factor = $type === 'revenue'
                    ? (1 + (float) $scenario->revenue_adjustment)
                    : (1 + (float) $scenario->expense_adjustment);
            } else {
                $factor = 1.0;
            }

            $projected = round($budgeted * $factor, 2);

            if ($type === 'revenue') {
                $projectedRevenue += $projected;
            } else {
                $projectedExpenses += $projected;
            }

            $projectedLines[] = [
                'line_id' => $line->id,
                'account_id' => $line->account_id ?? $line->gl_account_id,
                'type' => $type,
                'original_amount' => $budgeted,
                'projected_amount' => $projected,
            ];
        }

        return [
            'scenario_id' => $scenario->id,
            'projected_revenue' => round($projectedRevenue, 2),
            'projected_expenses' => round($projectedExpenses, 2),
            'projected_profit' => round($projectedRevenue - $projectedExpenses, 2),
            'lines' => $projectedLines,
        ];
    }

    /**
     * Compares two budgets.
     */
    public function compareBudgets(Budget $budgetA, Budget $budgetB): array
    {
        $linesA = $budgetA->lines()->get()->keyBy(fn ($l) => $l->account_id ?? $l->gl_account_id);
        $linesB = $budgetB->lines()->get()->keyBy(fn ($l) => $l->account_id ?? $l->gl_account_id);

        $allKeys = $linesA->keys()->merge($linesB->keys())->unique();

        $totalA = $linesA->sum(fn ($l) => (float) ($l->budgeted_amount ?? $l->budget_amount ?? 0));
        $totalB = $linesB->sum(fn ($l) => (float) ($l->budgeted_amount ?? $l->budget_amount ?? 0));

        $comparedLines = [];
        foreach ($allKeys as $key) {
            $a = isset($linesA[$key]) ? (float) ($linesA[$key]->budgeted_amount ?? $linesA[$key]->budget_amount ?? 0) : 0.0;
            $b = isset($linesB[$key]) ? (float) ($linesB[$key]->budgeted_amount ?? $linesB[$key]->budget_amount ?? 0) : 0.0;
            $comparedLines[] = [
                'account_id' => $key,
                'budget_a' => $a,
                'budget_b' => $b,
                'difference' => round($b - $a, 2),
            ];
        }

        return [
            'budget_a' => ['id' => $budgetA->id, 'name' => $budgetA->name, 'total' => round($totalA, 2)],
            'budget_b' => ['id' => $budgetB->id, 'name' => $budgetB->name, 'total' => round($totalB, 2)],
            'difference' => round($totalB - $totalA, 2),
            'lines' => $comparedLines,
        ];
    }

    /**
     * Creates a new scenario budget from a base budget.
     */
    public function createScenarioBudget(Budget $base, \Modules\Accounting\Models\BudgetScenario $scenario): Budget
    {
        $newBudget = Budget::create([
            'company_id' => $base->company_id,
            'parent_budget_id' => $base->id,
            'name' => $base->name . ' - ' . ($scenario->name ?? 'Scenario'),
            'fiscal_year' => $base->fiscal_year,
            'budget_period_start' => $base->budget_period_start,
            'budget_period_end' => $base->budget_period_end,
            'total_budget' => $base->total_budget,
            'total_spent' => 0,
            'status' => 'draft',
            'created_by' => $base->created_by,
        ]);

        foreach ($base->lines()->with('account', 'glAccount')->get() as $line) {
            $budgeted = (float) ($line->budgeted_amount ?? $line->budget_amount ?? 0);
            $type = optional($line->account)->type ?? optional($line->glAccount)->account_type ?? 'expense';

            if ($scenario->adjustment_type === 'percentage') {
                $factor = $type === 'revenue'
                    ? (1 + (float) $scenario->revenue_adjustment)
                    : (1 + (float) $scenario->expense_adjustment);
            } else {
                $factor = 1.0;
            }

            $newBudget->lines()->create([
                'account_id' => $line->account_id,
                'gl_account_id' => $line->gl_account_id,
                'period_month' => $line->period_month,
                'period_year' => $line->period_year,
                'budgeted_amount' => round($budgeted * $factor, 2),
                'actual_amount' => 0,
                'variance' => -round($budgeted * $factor, 2),
                'spent_amount' => 0,
                'category' => $line->category,
            ]);
        }

        return $newBudget->load('lines');
    }
}
