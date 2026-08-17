<?php

declare(strict_types=1);

namespace Modules\Accounting\Services;

use Modules\Accounting\Models\Budget;
use Modules\Accounting\Models\BudgetScenario;

/**
 * Budget "what-if" scenario planning. The actual revenue/expense projection
 * math lives in BudgetVarianceService::projectScenario() (already real and
 * tested) — this service is a thin orchestration layer on top of it plus
 * BudgetScenario persistence.
 */
class ScenarioPlanningService
{
    public function __construct(private readonly BudgetVarianceService $varianceService) {}

    public function listScenarios(): \Illuminate\Support\Collection
    {
        return BudgetScenario::with('baseBudget')->latest()->get();
    }

    public function createScenario(string $name, string $type, array $parameters): BudgetScenario
    {
        return BudgetScenario::create([
            'name' => $name,
            'scenario_type' => $type,
            'base_budget_id' => $parameters['base_budget_id'] ?? null,
            'adjustment_type' => $parameters['adjustment_type'] ?? 'percentage',
            'revenue_adjustment' => $parameters['revenue_adjustment'] ?? 0,
            'expense_adjustment' => $parameters['expense_adjustment'] ?? 0,
            'description' => $parameters['description'] ?? null,
            'assumptions' => $parameters,
            'status' => 'draft',
        ]);
    }

    public function runSimulation(int $scenarioId): array
    {
        return $this->varianceService->projectScenario(BudgetScenario::findOrFail($scenarioId));
    }

    public function compareScenarios(array $scenarioIds): array
    {
        return BudgetScenario::whereIn('id', $scenarioIds)->get()
            ->map(function (BudgetScenario $scenario) {
                return [
                    'scenario_id' => $scenario->id,
                    'scenario_name' => $scenario->name,
                ] + $this->varianceService->projectScenario($scenario);
            })
            ->all();
    }

    /**
     * Varies revenue_adjustment or expense_adjustment across [min, max] (in
     * percentage points) against the latest active budget and re-projects at
     * each step, using unsaved BudgetScenario instances.
     */
    public function sensitivityAnalysis(string $variable, float $rangeMin, float $rangeMax, float $step): array
    {
        $baseBudget = Budget::where('status', 'active')->latest('id')->first()
            ?? Budget::latest('id')->firstOrFail();

        $isRevenue = str_contains($variable, 'revenue');
        $step = $step > 0 ? $step : 10.0;

        $results = [];
        for ($value = $rangeMin; $value <= $rangeMax; $value += $step) {
            $scenario = new BudgetScenario([
                'name' => "sensitivity:{$variable}={$value}",
                'base_budget_id' => $baseBudget->id,
                'adjustment_type' => 'percentage',
                'revenue_adjustment' => $isRevenue ? $value / 100 : 0,
                'expense_adjustment' => $isRevenue ? 0 : $value / 100,
            ]);
            $scenario->setRelation('baseBudget', $baseBudget);

            $projection = $this->varianceService->projectScenario($scenario);

            $results[] = [
                'variable' => $variable,
                'value' => $value,
                'projected_revenue' => $projection['projected_revenue'],
                'projected_expenses' => $projection['projected_expenses'],
                'projected_profit' => $projection['projected_profit'],
            ];
        }

        return $results;
    }

    /**
     * Impact delta vs. an unadjusted (zero-adjustment) projection of the same
     * scenario, so both sides use identical line classification logic.
     */
    public function calculateImpact(int $scenarioId): array
    {
        $scenario = BudgetScenario::findOrFail($scenarioId);
        $projection = $this->varianceService->projectScenario($scenario);

        $baseline = clone $scenario;
        $baseline->revenue_adjustment = 0;
        $baseline->expense_adjustment = 0;
        $baselineProjection = $this->varianceService->projectScenario($baseline);

        $revenueImpact = round($projection['projected_revenue'] - $baselineProjection['projected_revenue'], 2);
        $costImpact = round($projection['projected_expenses'] - $baselineProjection['projected_expenses'], 2);
        $profitImpact = round($projection['projected_profit'] - $baselineProjection['projected_profit'], 2);

        return [
            'revenue' => $revenueImpact,
            'cost' => $costImpact,
            'profit' => $profitImpact,
            // Approximation: no fixed/variable cost split or cash-timing model
            // in this schema, so cashflow impact is treated as profit impact.
            'cashflow' => $profitImpact,
            'breakeven' => [
                'baseline_expenses' => $baselineProjection['projected_expenses'],
                'projected_expenses' => $projection['projected_expenses'],
                'note' => 'Approximation: revenue needed to cover projected expenses; no fixed/variable cost distinction in this schema.',
            ],
        ];
    }

    public function approveScenario(int $scenarioId): BudgetScenario
    {
        $scenario = BudgetScenario::findOrFail($scenarioId);
        $scenario->update(['status' => 'approved', 'approved_at' => now()]);

        return $scenario->fresh();
    }
}
