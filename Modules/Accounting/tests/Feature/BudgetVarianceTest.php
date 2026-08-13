<?php

use Illuminate\Support\Facades\Auth;
use Modules\Accounting\Models\Budget;
use Modules\Accounting\Models\BudgetLine;
use Modules\Accounting\Models\BudgetScenario;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Services\BudgetVarianceService;

describe('Budget Variance Analysis', function () {
    beforeEach(function () {
        $this->user = actingAsUser('admin');
        $this->service = app(BudgetVarianceService::class);
    });

    // ─── Model unit tests ─────────────────────────────────────────────────────

    test('Budget::varianceSummary() returns correct net variance', function () {
        $budget = Budget::factory()->create(['created_by' => $this->user->id]);

        $revenueAccount = ChartOfAccount::factory()->create(['type' => 'revenue']);
        $expenseAccount = ChartOfAccount::factory()->create(['type' => 'expense']);

        BudgetLine::factory()->create([
            'budget_id' => $budget->id,
            'account_id' => $revenueAccount->id,
            'period_month' => 1,
            'period_year' => 2026,
            'budgeted_amount' => 100000,
            'actual_amount' => 110000,
            'variance' => 10000,
        ]);

        BudgetLine::factory()->create([
            'budget_id' => $budget->id,
            'account_id' => $expenseAccount->id,
            'period_month' => 1,
            'period_year' => 2026,
            'budgeted_amount' => 80000,
            'actual_amount' => 85000,
            'variance' => 5000,
        ]);

        $summary = $budget->varianceSummary();

        expect($summary)->toHaveKeys(['revenue_variance', 'expense_variance', 'net_variance', 'variance_pct']);
        expect($summary['revenue_variance'])->toBe(10000.0);
        expect($summary['expense_variance'])->toBe(5000.0);
        // net_variance = revenue_variance - expense_variance = 10000 - 5000 = 5000
        expect($summary['net_variance'])->toBe(5000.0);
    });

    test('Budget::isOverBudget() returns true when actual expenses exceed budgeted', function () {
        $budget = Budget::factory()->create(['created_by' => $this->user->id]);

        $expenseAccount = ChartOfAccount::factory()->create(['type' => 'expense']);

        BudgetLine::factory()->create([
            'budget_id' => $budget->id,
            'account_id' => $expenseAccount->id,
            'period_month' => 1,
            'period_year' => 2026,
            'budgeted_amount' => 50000,
            'actual_amount' => 75000,
            'variance' => 25000,
        ]);

        expect($budget->isOverBudget())->toBeTrue();
    });

    test('Budget::isOverBudget() returns false when expenses are within budget', function () {
        $budget = Budget::factory()->create(['created_by' => $this->user->id]);

        $expenseAccount = ChartOfAccount::factory()->create(['type' => 'expense']);

        BudgetLine::factory()->create([
            'budget_id' => $budget->id,
            'account_id' => $expenseAccount->id,
            'period_month' => 1,
            'period_year' => 2026,
            'budgeted_amount' => 50000,
            'actual_amount' => 40000,
            'variance' => -10000,
        ]);

        expect($budget->isOverBudget())->toBeFalse();
    });

    test('BudgetLine::variancePercent() computes correct percentage', function () {
        $budget = Budget::factory()->create(['created_by' => $this->user->id]);
        $account = ChartOfAccount::factory()->create(['type' => 'expense']);

        $line = BudgetLine::factory()->create([
            'budget_id' => $budget->id,
            'account_id' => $account->id,
            'period_month' => 1,
            'period_year' => 2026,
            'budgeted_amount' => 10000,
            'actual_amount' => 12000,
            'spent_amount' => 12000,
            'variance' => 2000,
        ]);

        expect($line->variancePercent())->toBe(20.0);
    });

    // ─── Service tests ────────────────────────────────────────────────────────

    test('BudgetVarianceService::varianceReport() returns expected keys', function () {
        $budget = Budget::factory()->create(['created_by' => $this->user->id]);
        $account = ChartOfAccount::factory()->create(['type' => 'expense']);

        BudgetLine::factory()->create([
            'budget_id' => $budget->id,
            'account_id' => $account->id,
            'period_month' => 1,
            'period_year' => 2026,
            'budgeted_amount' => 50000,
            'actual_amount' => 45000,
            'variance' => -5000,
        ]);

        $report = $this->service->varianceReport($budget);

        expect($report)->toHaveKeys(['budget_id', 'budget_name', 'fiscal_year', 'accounts', 'category_summaries', 'ytd']);
        expect($report['ytd'])->toHaveKeys(['budgeted', 'actual', 'variance', 'variance_pct']);
    });

    test('BudgetVarianceService::monthlyTrend() returns 12 months', function () {
        $budget = Budget::factory()->create(['created_by' => $this->user->id]);

        $trend = $this->service->monthlyTrend($budget);

        expect($trend)->toHaveCount(12);
        expect($trend[0])->toHaveKeys(['month', 'month_name', 'year', 'budgeted', 'actual', 'variance', 'cumulative_variance']);
        expect($trend[0]['month'])->toBe(1);
        expect($trend[11]['month'])->toBe(12);
    });

    test('BudgetVarianceService::topVariances() returns top N by variance', function () {
        $budget = Budget::factory()->create(['created_by' => $this->user->id]);

        // Create 5 lines with different variances
        $amounts = [50000, 10000, 30000, 80000, 20000];
        foreach ($amounts as $i => $variance) {
            $account = ChartOfAccount::factory()->create(['type' => 'expense']);
            BudgetLine::factory()->create([
                'budget_id' => $budget->id,
                'account_id' => $account->id,
                'period_month' => $i + 1,
                'period_year' => 2026,
                'budgeted_amount' => 100000,
                'actual_amount' => 100000 + $variance,
                'variance' => $variance,
            ]);
        }

        $top3 = $this->service->topVariances($budget, 3);

        expect($top3)->toHaveCount(3);
        // The highest variance is 80000
        expect($top3[0]['variance'])->toBe(80000.0);
        expect($top3[0])->toHaveKeys(['line_id', 'account_id', 'budgeted_amount', 'actual_amount', 'variance', 'variance_pct']);
    });

    test('BudgetVarianceService::projectScenario() applies percentage adjustments correctly', function () {
        $budget = Budget::factory()->create(['created_by' => $this->user->id]);

        $revenueAccount = ChartOfAccount::factory()->create(['type' => 'revenue']);
        $expenseAccount = ChartOfAccount::factory()->create(['type' => 'expense']);

        BudgetLine::factory()->create([
            'budget_id' => $budget->id,
            'account_id' => $revenueAccount->id,
            'period_month' => 1,
            'period_year' => 2026,
            'budgeted_amount' => 100000,
            'actual_amount' => 0,
            'variance' => -100000,
        ]);

        BudgetLine::factory()->create([
            'budget_id' => $budget->id,
            'account_id' => $expenseAccount->id,
            'period_month' => 1,
            'period_year' => 2026,
            'budgeted_amount' => 80000,
            'actual_amount' => 0,
            'variance' => -80000,
        ]);

        $scenario = BudgetScenario::factory()->create([
            'base_budget_id' => $budget->id,
            'adjustment_type' => 'percentage',
            'revenue_adjustment' => 0.15,   // +15%
            'expense_adjustment' => -0.10,  // -10%
        ]);

        $projection = $this->service->projectScenario($scenario);

        expect($projection)->toHaveKeys(['scenario_id', 'projected_revenue', 'projected_expenses', 'projected_profit', 'lines']);
        // Revenue: 100000 * 1.15 = 115000
        expect($projection['projected_revenue'])->toBe(115000.0);
        // Expenses: 80000 * 0.90 = 72000
        expect($projection['projected_expenses'])->toBe(72000.0);
    });

    test('BudgetVarianceService::compareBudgets() returns comparison structure', function () {
        $budgetA = Budget::factory()->create(['created_by' => $this->user->id]);
        $budgetB = Budget::factory()->create(['created_by' => $this->user->id]);

        $account = ChartOfAccount::factory()->create(['type' => 'expense']);

        BudgetLine::factory()->create([
            'budget_id' => $budgetA->id,
            'account_id' => $account->id,
            'period_month' => 1,
            'period_year' => 2026,
            'budgeted_amount' => 50000,
            'actual_amount' => 0,
            'variance' => -50000,
        ]);

        BudgetLine::factory()->create([
            'budget_id' => $budgetB->id,
            'account_id' => $account->id,
            'period_month' => 1,
            'period_year' => 2026,
            'budgeted_amount' => 60000,
            'actual_amount' => 0,
            'variance' => -60000,
        ]);

        $comparison = $this->service->compareBudgets($budgetA, $budgetB);

        expect($comparison)->toHaveKeys(['budget_a', 'budget_b', 'difference', 'lines']);
        expect($comparison['budget_a']['id'])->toBe($budgetA->id);
        expect($comparison['budget_b']['id'])->toBe($budgetB->id);
        expect($comparison['difference'])->toBe(10000.0);
        expect($comparison['lines'])->toBeArray();
        expect($comparison['lines'][0])->toHaveKeys(['account_id', 'budget_a', 'budget_b', 'difference']);
    });

    test('BudgetVarianceService::createScenarioBudget() creates new Budget with adjusted lines', function () {
        $base = Budget::factory()->create(['created_by' => $this->user->id]);
        $account = ChartOfAccount::factory()->create(['type' => 'revenue']);

        BudgetLine::factory()->create([
            'budget_id' => $base->id,
            'account_id' => $account->id,
            'period_month' => 1,
            'period_year' => 2026,
            'budgeted_amount' => 100000,
            'actual_amount' => 0,
            'variance' => -100000,
        ]);

        $scenario = BudgetScenario::factory()->create([
            'base_budget_id' => $base->id,
            'adjustment_type' => 'percentage',
            'revenue_adjustment' => 0.20,
            'expense_adjustment' => 0.05,
        ]);

        $newBudget = $this->service->createScenarioBudget($base, $scenario);

        expect($newBudget)->toBeInstanceOf(Budget::class);
        expect($newBudget->parent_budget_id)->toBe($base->id);
        expect($newBudget->lines)->toHaveCount(1);
        // Revenue line: 100000 * 1.20 = 120000
        expect((float) $newBudget->lines->first()->budgeted_amount)->toBe(120000.0);
    });

    // ─── API tests ────────────────────────────────────────────────────────────

    test('GET /budgets returns 200', function () {
        Budget::factory()->count(3)->create(['created_by' => $this->user->id]);

        $this->getJson('/api/v1/accounting/budgets')
            ->assertStatus(200)
            ->assertJsonStructure(['data', 'total']);
    });

    test('POST /budgets creates a budget with 201', function () {
        $this->postJson('/api/v1/accounting/budgets', [
            'name' => 'FY2026 Operating Budget',
            'fiscal_year' => 2026,
            'fiscal_year_start' => '2026-01-01',
            'fiscal_year_end' => '2026-12-31',
            'status' => 'draft',
        ])
            ->assertStatus(201)
            ->assertJsonFragment(['name' => 'FY2026 Operating Budget', 'fiscal_year' => 2026]);
    });

    test('GET /budgets/{id} returns 200 with variance summary', function () {
        $budget = Budget::factory()->create(['created_by' => $this->user->id]);

        $this->getJson("/api/v1/accounting/budgets/{$budget->id}")
            ->assertStatus(200)
            ->assertJsonStructure(['budget', 'variance_summary', 'completion_pct', 'is_over_budget']);
    });

    test('POST /budgets/{id}/sync-actuals returns 200', function () {
        $budget = Budget::factory()->create(['created_by' => $this->user->id]);

        $this->postJson("/api/v1/accounting/budgets/{$budget->id}/sync-actuals")
            ->assertStatus(200)
            ->assertJsonStructure(['message', 'budget', 'variance_summary']);
    });

    test('GET /budgets/{id}/variance-report returns 200 with report data', function () {
        $budget = Budget::factory()->create(['created_by' => $this->user->id]);
        $account = ChartOfAccount::factory()->create(['type' => 'expense']);

        BudgetLine::factory()->create([
            'budget_id' => $budget->id,
            'account_id' => $account->id,
            'period_month' => 1,
            'period_year' => 2026,
            'budgeted_amount' => 50000,
            'actual_amount' => 45000,
            'variance' => -5000,
        ]);

        $this->getJson("/api/v1/accounting/budgets/{$budget->id}/variance-report")
            ->assertStatus(200)
            ->assertJsonStructure(['budget_id', 'budget_name', 'fiscal_year', 'accounts', 'category_summaries', 'ytd']);
    });

    test('GET /budgets/{id}/monthly-trend returns 200 with 12 months', function () {
        $budget = Budget::factory()->create(['created_by' => $this->user->id]);

        $response = $this->getJson("/api/v1/accounting/budgets/{$budget->id}/monthly-trend")
            ->assertStatus(200)
            ->assertJsonStructure(['budget_id', 'fiscal_year', 'trend']);

        expect($response->json('trend'))->toHaveCount(12);
    });

    test('GET /budgets/{id}/top-variances returns 200', function () {
        $budget = Budget::factory()->create(['created_by' => $this->user->id]);

        $this->getJson("/api/v1/accounting/budgets/{$budget->id}/top-variances")
            ->assertStatus(200)
            ->assertJsonStructure(['budget_id', 'limit', 'variances']);
    });

    test('POST /budgets/{id}/lines creates a budget line with 201', function () {
        $budget = Budget::factory()->create(['created_by' => $this->user->id]);
        $account = ChartOfAccount::factory()->create(['type' => 'expense']);

        $this->postJson("/api/v1/accounting/budgets/{$budget->id}/lines", [
            'account_id' => $account->id,
            'period_month' => 3,
            'period_year' => 2026,
            'budgeted_amount' => 25000,
        ])
            ->assertStatus(201)
            ->assertJsonFragment(['account_id' => $account->id, 'period_month' => 3]);
    });

    test('GET /budgets/{id}/scenarios returns 200', function () {
        $budget = Budget::factory()->create(['created_by' => $this->user->id]);
        BudgetScenario::factory()->create(['base_budget_id' => $budget->id]);

        $this->getJson("/api/v1/accounting/budgets/{$budget->id}/scenarios")
            ->assertStatus(200)
            ->assertJsonStructure([['id', 'name', 'scenario_type']]);
    });

    test('POST /budgets/{id}/scenarios creates a scenario with 201', function () {
        $budget = Budget::factory()->create(['created_by' => $this->user->id]);

        $this->postJson("/api/v1/accounting/budgets/{$budget->id}/scenarios", [
            'name' => 'My Optimistic',
            'scenario_type' => 'optimistic',
            'adjustment_type' => 'percentage',
            'revenue_adjustment' => 0.15,
            'expense_adjustment' => -0.05,
        ])
            ->assertStatus(201)
            ->assertJsonFragment(['name' => 'My Optimistic', 'scenario_type' => 'optimistic']);
    });

    test('GET /budget-scenarios/{id}/project returns 200 with projections', function () {
        $budget = Budget::factory()->create(['created_by' => $this->user->id]);
        $scenario = BudgetScenario::factory()->create(['base_budget_id' => $budget->id]);

        $this->getJson("/api/v1/accounting/budget-scenarios/{$scenario->id}/project")
            ->assertStatus(200)
            ->assertJsonStructure(['scenario_id', 'projected_revenue', 'projected_expenses', 'projected_profit', 'lines']);
    });

    test('unauthenticated requests return 401', function () {
        // Log out any authenticated user and hit the API
        auth()->forgetGuards();
        Auth::shouldUse('web');

        $this->getJson('/api/v1/accounting/budgets', ['Accept' => 'application/json'])
            ->assertStatus(401);
    });
});
