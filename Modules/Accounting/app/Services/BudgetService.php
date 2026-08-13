<?php

declare(strict_types=1);

namespace Modules\Accounting\Services;

use Illuminate\Support\Collection;
use Modules\Accounting\Models\Budget;
use Modules\Accounting\Models\BudgetLine;

class BudgetService
{
    /**
     * Create a budget with optional lines.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function createBudget(array $data, array $lines = []): Budget
    {
        $budget = Budget::create($data);

        foreach ($lines as $lineData) {
            $this->addLine($budget, $lineData);
        }

        return $budget->refresh();
    }

    /**
     * Add a line to a budget and recompute the budget total.
     *
     * @param  array<string, mixed>  $data
     */
    public function addLine(Budget $budget, array $data): BudgetLine
    {
        $data['budget_id'] = $budget->id;

        $line = BudgetLine::create($data);

        // Update total_budget to sum of all budgeted amounts
        $totalBudget = (float) $budget->lines()->sum('budgeted_amount');
        $budget->update(['total_budget' => $totalBudget]);

        return $line;
    }

    /**
     * Record actual spending against a budget line.
     */
    public function recordSpend(BudgetLine $line, float $amount): BudgetLine
    {
        $line->recordSpend($amount);

        return $line->fresh();
    }

    /**
     * Approve a budget.
     */
    public function approveBudget(Budget $budget, int $userId): Budget
    {
        $budget->approve($userId);

        return $budget->fresh();
    }

    /**
     * Get variance report for a budget: per-line breakdown.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getVarianceReport(Budget $budget): array
    {
        $lines = $budget->lines()->get();

        return $lines->map(function (BudgetLine $line) {
            $budgeted = (float) $line->budgeted_amount;
            $spent = (float) $line->spent_amount;
            $variance = $budgeted - $spent;

            return [
                'id' => $line->id,
                'category' => $line->category,
                'description' => $line->description,
                'budgeted' => $budgeted,
                'spent' => $spent,
                'variance' => $variance,
                'utilization_pct' => $budgeted > 0 ? round(($spent / $budgeted) * 100, 2) : 0.0,
            ];
        })->values()->all();
    }

    /**
     * Get budget summary across all active budgets.
     *
     * @return array{total_budgeted: float, total_spent: float, overall_utilization: float}
     */
    public function getBudgetSummary(): array
    {
        $budgets = Budget::where('status', 'active')->get();

        $totalBudgeted = (float) $budgets->sum('total_budget');
        $totalSpent = (float) $budgets->sum('total_spent');

        return [
            'total_budgeted' => $totalBudgeted,
            'total_spent' => $totalSpent,
            'overall_utilization' => $totalBudgeted > 0 ? round(($totalSpent / $totalBudgeted) * 100, 2) : 0.0,
        ];
    }

    /**
     * Get budgets for a fiscal year.
     */
    public function getBudgetsByYear(int $year): Collection
    {
        return Budget::where('fiscal_year', $year)->get();
    }

    /**
     * Get over-budget lines across all active budgets.
     */
    public function getOverBudgetLines(): Collection
    {
        return BudgetLine::whereHas('budget', fn ($q) => $q->where('status', 'active'))
            ->whereColumn('spent_amount', '>', 'budgeted_amount')
            ->with('budget')
            ->get();
    }

    /**
     * Get department breakdown: [{department, total_budget, total_spent, utilization_rate}]
     *
     * @return array<int, array<string, mixed>>
     */
    public function getDepartmentBreakdown(): array
    {
        $budgets = Budget::whereNotNull('department')
            ->where('status', 'active')
            ->get()
            ->groupBy('department');

        $breakdown = [];

        foreach ($budgets as $department => $deptBudgets) {
            $totalBudget = (float) $deptBudgets->sum('total_budget');
            $totalSpent = (float) $deptBudgets->sum('total_spent');

            $breakdown[] = [
                'department' => $department,
                'total_budget' => $totalBudget,
                'total_spent' => $totalSpent,
                'utilization_rate' => $totalBudget > 0 ? round(($totalSpent / $totalBudget) * 100, 2) : 0.0,
            ];
        }

        return $breakdown;
    }

    /**
     * Clone a budget to a new year (copy structure, reset spent amounts to 0).
     */
    public function cloneBudget(Budget $budget, int $newYear): Budget
    {
        $newBudget = Budget::create([
            'name' => $budget->name.' ('.$newYear.')',
            'description' => $budget->description,
            'fiscal_year' => $newYear,
            'fiscal_year_start' => $budget->fiscal_year_start,
            'fiscal_year_end' => $budget->fiscal_year_end,
            'status' => 'draft',
            'scenario' => $budget->scenario ?? 'base',
            'parent_budget_id' => $budget->parent_budget_id,
            'total_revenue_budget' => $budget->total_revenue_budget,
            'total_expense_budget' => $budget->total_expense_budget,
            'created_by' => $budget->created_by,
            'department' => $budget->department,
            'total_budget' => $budget->total_budget,
            'total_spent' => 0,
            'currency' => $budget->currency,
            'notes' => $budget->notes,
        ]);

        foreach ($budget->lines()->get() as $line) {
            BudgetLine::create([
                'budget_id' => $newBudget->id,
                'account_id' => $line->account_id,
                'period_month' => $line->period_month,
                'period_year' => $line->period_year,
                'budgeted_amount' => $line->budgeted_amount,
                'actual_amount' => 0,
                'variance' => 0,
                'notes' => $line->notes,
                'category' => $line->category,
                'description' => $line->description,
                'spent_amount' => 0,
                'period' => $line->period,
                'month' => $line->month,
                'quarter' => $line->quarter,
            ]);
        }

        return $newBudget->load('lines');
    }
}
