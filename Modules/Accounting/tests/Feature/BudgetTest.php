<?php

declare(strict_types=1);

use Modules\Accounting\Models\Budget;
use Modules\Accounting\Models\BudgetLine;
use Modules\Accounting\Services\BudgetService;

describe('Budget Management', function () {
    beforeEach(function () {
        $this->user = actingAsUser('admin');
        $this->service = app(BudgetService::class);
    });

    // ─── Budget Model Unit Tests ───────────────────────────────────────────────

    test('Budget::isDraft() returns true when status is draft', function () {
        $budget = Budget::factory()->create([
            'created_by' => $this->user->id,
            'status' => 'draft',
        ]);

        expect($budget->isDraft())->toBeTrue();
        expect($budget->isActive())->toBeFalse();
    });

    test('Budget::isActive() returns true when status is active', function () {
        $budget = Budget::factory()->create([
            'created_by' => $this->user->id,
            'status' => 'active',
        ]);

        expect($budget->isActive())->toBeTrue();
        expect($budget->isDraft())->toBeFalse();
    });

    test('Budget::remainingBudget() returns correct value', function () {
        $budget = Budget::factory()->create([
            'created_by' => $this->user->id,
            'total_budget' => 100000,
            'total_spent' => 35000,
        ]);

        expect($budget->remainingBudget())->toBe(65000.0);
    });

    test('Budget::utilizationRate() returns correct percentage', function () {
        $budget = Budget::factory()->create([
            'created_by' => $this->user->id,
            'total_budget' => 100000,
            'total_spent' => 50000,
        ]);

        expect($budget->utilizationRate())->toBe(50.0);
    });

    test('Budget::utilizationRate() returns 0.0 when total_budget is zero', function () {
        $budget = Budget::factory()->create([
            'created_by' => $this->user->id,
            'total_budget' => 0,
            'total_spent' => 0,
        ]);

        expect($budget->utilizationRate())->toBe(0.0);
    });

    test('Budget::isOverBudget() returns true when total_spent exceeds total_budget', function () {
        $budget = Budget::factory()->create([
            'created_by' => $this->user->id,
            'total_budget' => 50000,
            'total_spent' => 75000,
        ]);

        expect($budget->isOverBudget())->toBeTrue();
    });

    test('Budget::isOverBudget() returns false when within budget', function () {
        $budget = Budget::factory()->create([
            'created_by' => $this->user->id,
            'total_budget' => 100000,
            'total_spent' => 40000,
        ]);

        expect($budget->isOverBudget())->toBeFalse();
    });

    test('Budget::variance() returns positive value when under budget', function () {
        $budget = Budget::factory()->create([
            'created_by' => $this->user->id,
            'total_budget' => 100000,
            'total_spent' => 80000,
        ]);

        expect($budget->variance())->toBe(20000.0);
    });

    test('Budget::approve() sets status to approved and records approver', function () {
        $budget = Budget::factory()->create([
            'created_by' => $this->user->id,
            'status' => 'draft',
        ]);

        $budget->approve($this->user->id);
        $budget->refresh();

        expect($budget->status)->toBe('approved');
        expect($budget->approved_by)->toBe($this->user->id);
        expect($budget->approved_at)->not->toBeNull();
    });

    test('Budget::activate() sets status to active', function () {
        $budget = Budget::factory()->create([
            'created_by' => $this->user->id,
            'status' => 'approved',
        ]);

        $budget->activate();
        $budget->refresh();

        expect($budget->status)->toBe('active');
    });

    test('Budget::recompute() sums spent_amount from lines', function () {
        $budget = Budget::factory()->create([
            'created_by' => $this->user->id,
            'total_spent' => 0,
        ]);

        BudgetLine::factory()->create([
            'budget_id' => $budget->id,
            'spent_amount' => 20000,
            'account_id' => null,
        ]);

        BudgetLine::factory()->create([
            'budget_id' => $budget->id,
            'spent_amount' => 15000,
            'account_id' => null,
        ]);

        $budget->recompute();
        $budget->refresh();

        expect((float) $budget->total_spent)->toBe(35000.0);
    });

    // ─── BudgetLine Model Unit Tests ──────────────────────────────────────────

    test('BudgetLine::remainingAmount() returns correct value', function () {
        $budget = Budget::factory()->create(['created_by' => $this->user->id]);

        $line = BudgetLine::factory()->create([
            'budget_id' => $budget->id,
            'budgeted_amount' => 50000,
            'spent_amount' => 30000,
            'account_id' => null,
        ]);

        expect($line->remainingAmount())->toBe(20000.0);
    });

    test('BudgetLine::utilizationRate() returns correct percentage', function () {
        $budget = Budget::factory()->create(['created_by' => $this->user->id]);

        $line = BudgetLine::factory()->create([
            'budget_id' => $budget->id,
            'budgeted_amount' => 100000,
            'spent_amount' => 25000,
            'account_id' => null,
        ]);

        expect($line->utilizationRate())->toBe(25.0);
    });

    test('BudgetLine::isOverBudget() returns true when spent exceeds budgeted', function () {
        $budget = Budget::factory()->create(['created_by' => $this->user->id]);

        $line = BudgetLine::factory()->create([
            'budget_id' => $budget->id,
            'budgeted_amount' => 10000,
            'spent_amount' => 15000,
            'account_id' => null,
        ]);

        expect($line->isOverBudget())->toBeTrue();
    });

    test('BudgetLine::variance() returns budgeted minus spent', function () {
        $budget = Budget::factory()->create(['created_by' => $this->user->id]);

        $line = BudgetLine::factory()->create([
            'budget_id' => $budget->id,
            'budgeted_amount' => 50000,
            'spent_amount' => 20000,
            'account_id' => null,
        ]);

        expect($line->variance())->toBe(30000.0);
    });

    test('BudgetLine::recordSpend() increments spent_amount and recomputes budget', function () {
        $budget = Budget::factory()->create([
            'created_by' => $this->user->id,
            'total_spent' => 0,
        ]);

        $line = BudgetLine::factory()->create([
            'budget_id' => $budget->id,
            'spent_amount' => 10000,
            'account_id' => null,
        ]);

        $line->recordSpend(5000.0);
        $line->refresh();

        expect((float) $line->spent_amount)->toBe(15000.0);

        $budget->refresh();
        expect((float) $budget->total_spent)->toBe(15000.0);
    });

    // ─── BudgetService Unit Tests ─────────────────────────────────────────────

    test('BudgetService::createBudget() creates budget without lines', function () {
        $budget = $this->service->createBudget([
            'name' => 'Annual IT Budget',
            'fiscal_year' => 2026,
            'fiscal_year_start' => '2026-01-01',
            'fiscal_year_end' => '2026-12-31',
            'status' => 'draft',
            'total_budget' => 200000,
            'total_spent' => 0,
            'currency' => 'USD',
            'created_by' => $this->user->id,
        ]);

        expect($budget)->toBeInstanceOf(Budget::class);
        expect($budget->name)->toBe('Annual IT Budget');
        expect($budget->lines()->count())->toBe(0);
    });

    test('BudgetService::createBudget() creates budget with lines', function () {
        $budget = $this->service->createBudget(
            [
                'name' => 'Annual Budget',
                'fiscal_year' => 2026,
                'fiscal_year_start' => '2026-01-01',
                'fiscal_year_end' => '2026-12-31',
                'status' => 'draft',
                'total_budget' => 0,
                'total_spent' => 0,
                'currency' => 'USD',
                'created_by' => $this->user->id,
            ],
            [
                ['category' => 'salaries', 'budgeted_amount' => 100000, 'period' => 'annual'],
                ['category' => 'marketing', 'budgeted_amount' => 50000, 'period' => 'annual'],
            ]
        );

        expect($budget->lines()->count())->toBe(2);
    });

    test('BudgetService::addLine() adds line and updates total_budget', function () {
        $budget = Budget::factory()->create([
            'created_by' => $this->user->id,
            'total_budget' => 0,
        ]);

        $line = $this->service->addLine($budget, [
            'category' => 'rent',
            'budgeted_amount' => 24000,
            'period' => 'annual',
        ]);

        expect($line)->toBeInstanceOf(BudgetLine::class);
        expect($line->category)->toBe('rent');

        $budget->refresh();
        expect((float) $budget->total_budget)->toBe(24000.0);
    });

    test('BudgetService::recordSpend() increments spent_amount on line', function () {
        $budget = Budget::factory()->create([
            'created_by' => $this->user->id,
            'total_spent' => 0,
        ]);

        $line = BudgetLine::factory()->create([
            'budget_id' => $budget->id,
            'spent_amount' => 0,
            'account_id' => null,
        ]);

        $updatedLine = $this->service->recordSpend($line, 5000.0);

        expect((float) $updatedLine->spent_amount)->toBe(5000.0);
    });

    test('BudgetService::approveBudget() sets approved status', function () {
        $budget = Budget::factory()->create([
            'created_by' => $this->user->id,
            'status' => 'draft',
        ]);

        $approved = $this->service->approveBudget($budget, $this->user->id);

        expect($approved->status)->toBe('approved');
        expect($approved->approved_by)->toBe($this->user->id);
    });

    test('BudgetService::getVarianceReport() returns per-line breakdown', function () {
        $budget = Budget::factory()->create([
            'created_by' => $this->user->id,
            'total_budget' => 100000,
        ]);

        BudgetLine::factory()->create([
            'budget_id' => $budget->id,
            'category' => 'salaries',
            'budgeted_amount' => 80000,
            'spent_amount' => 60000,
            'account_id' => null,
        ]);

        $report = $this->service->getVarianceReport($budget);

        expect($report)->toBeArray();
        expect(count($report))->toBe(1);
        expect($report[0])->toHaveKeys(['category', 'budgeted', 'spent', 'variance', 'utilization_pct']);
        expect($report[0]['category'])->toBe('salaries');
        expect($report[0]['variance'])->toBe(20000.0);
        expect($report[0]['utilization_pct'])->toBe(75.0);
    });

    test('BudgetService::getBudgetSummary() returns totals across active budgets', function () {
        Budget::factory()->create([
            'created_by' => $this->user->id,
            'status' => 'active',
            'total_budget' => 100000,
            'total_spent' => 40000,
        ]);

        Budget::factory()->create([
            'created_by' => $this->user->id,
            'status' => 'active',
            'total_budget' => 200000,
            'total_spent' => 60000,
        ]);

        // This draft budget should NOT be included
        Budget::factory()->create([
            'created_by' => $this->user->id,
            'status' => 'draft',
            'total_budget' => 50000,
            'total_spent' => 10000,
        ]);

        $summary = $this->service->getBudgetSummary();

        expect($summary)->toHaveKeys(['total_budgeted', 'total_spent', 'overall_utilization']);
        expect($summary['total_budgeted'])->toBe(300000.0);
        expect($summary['total_spent'])->toBe(100000.0);
        expect($summary['overall_utilization'])->toBe(33.33);
    });

    test('BudgetService::getBudgetsByYear() returns budgets for the given year', function () {
        Budget::factory()->create(['created_by' => $this->user->id, 'fiscal_year' => 2026]);
        Budget::factory()->create(['created_by' => $this->user->id, 'fiscal_year' => 2026]);
        Budget::factory()->create(['created_by' => $this->user->id, 'fiscal_year' => 2025]);

        $budgets = $this->service->getBudgetsByYear(2026);

        expect($budgets->count())->toBeGreaterThanOrEqual(2);
        expect($budgets->every(fn ($b) => $b->fiscal_year === 2026))->toBeTrue();
    });

    test('BudgetService::getOverBudgetLines() returns only over-budget lines from active budgets', function () {
        $activeBudget = Budget::factory()->create([
            'created_by' => $this->user->id,
            'status' => 'active',
            'total_budget' => 100000,
            'total_spent' => 0,
        ]);

        $draftBudget = Budget::factory()->create([
            'created_by' => $this->user->id,
            'status' => 'draft',
            'total_budget' => 50000,
            'total_spent' => 0,
        ]);

        // Over-budget line in active budget
        BudgetLine::factory()->create([
            'budget_id' => $activeBudget->id,
            'budgeted_amount' => 10000,
            'spent_amount' => 15000,
            'account_id' => null,
        ]);

        // Under-budget line in active budget
        BudgetLine::factory()->create([
            'budget_id' => $activeBudget->id,
            'budgeted_amount' => 10000,
            'spent_amount' => 5000,
            'account_id' => null,
        ]);

        // Over-budget line in draft budget (should NOT appear)
        BudgetLine::factory()->create([
            'budget_id' => $draftBudget->id,
            'budgeted_amount' => 10000,
            'spent_amount' => 20000,
            'account_id' => null,
        ]);

        $overLines = $this->service->getOverBudgetLines();

        expect($overLines->count())->toBe(1);
        expect((float) $overLines->first()->spent_amount)->toBeGreaterThan((float) $overLines->first()->budgeted_amount);
    });

    test('BudgetService::getDepartmentBreakdown() returns grouped department data', function () {
        Budget::factory()->create([
            'created_by' => $this->user->id,
            'status' => 'active',
            'department' => 'Engineering',
            'total_budget' => 100000,
            'total_spent' => 50000,
        ]);

        Budget::factory()->create([
            'created_by' => $this->user->id,
            'status' => 'active',
            'department' => 'Marketing',
            'total_budget' => 50000,
            'total_spent' => 20000,
        ]);

        $breakdown = $this->service->getDepartmentBreakdown();

        expect($breakdown)->toBeArray();
        expect(count($breakdown))->toBeGreaterThanOrEqual(2);

        $departments = array_column($breakdown, 'department');
        expect(in_array('Engineering', $departments))->toBeTrue();
        expect(in_array('Marketing', $departments))->toBeTrue();

        $engRow = collect($breakdown)->firstWhere('department', 'Engineering');
        expect($engRow['total_budget'])->toBe(100000.0);
        expect($engRow['utilization_rate'])->toBe(50.0);
    });

    test('BudgetService::cloneBudget() creates a new budget for next year with reset spent', function () {
        $budget = Budget::factory()->create([
            'created_by' => $this->user->id,
            'fiscal_year' => 2026,
            'status' => 'active',
            'total_budget' => 100000,
            'total_spent' => 60000,
            'department' => 'Sales',
        ]);

        BudgetLine::factory()->create([
            'budget_id' => $budget->id,
            'category' => 'salaries',
            'budgeted_amount' => 80000,
            'spent_amount' => 60000,
            'account_id' => null,
        ]);

        $cloned = $this->service->cloneBudget($budget, 2027);

        expect($cloned->fiscal_year)->toBe(2027);
        expect($cloned->status)->toBe('draft');
        expect((float) $cloned->total_spent)->toBe(0.0);
        expect((float) $cloned->total_budget)->toBe(100000.0);
        expect($cloned->lines)->toHaveCount(1);
        expect((float) $cloned->lines->first()->spent_amount)->toBe(0.0);
        expect((float) $cloned->lines->first()->budgeted_amount)->toBe(80000.0);
    });

    // ─── API Tests ────────────────────────────────────────────────────────────

    test('GET /api/v1/accounting/budgets returns 200', function () {
        Budget::factory()->count(3)->create(['created_by' => $this->user->id]);

        $this->getJson('/api/v1/accounting/budgets')
            ->assertStatus(200);
    });

    test('POST /api/v1/accounting/budgets creates a budget with 201', function () {
        $this->postJson('/api/v1/accounting/budgets', [
            'name' => 'FY2026 Department Budget',
            'fiscal_year' => 2026,
            'fiscal_year_start' => '2026-01-01',
            'fiscal_year_end' => '2026-12-31',
            'status' => 'draft',
            'total_budget' => 500000,
            'currency' => 'USD',
        ])
            ->assertStatus(201)
            ->assertJsonFragment(['name' => 'FY2026 Department Budget']);
    });

    test('GET /api/v1/accounting/budgets/{id} returns budget with lines', function () {
        $budget = Budget::factory()->create(['created_by' => $this->user->id]);
        BudgetLine::factory()->create([
            'budget_id' => $budget->id,
            'account_id' => null,
        ]);

        $this->getJson("/api/v1/accounting/budgets/{$budget->id}")
            ->assertStatus(200);
    });

    test('PUT /api/v1/accounting/budgets/{id} updates budget', function () {
        $budget = Budget::factory()->create([
            'created_by' => $this->user->id,
            'status' => 'draft',
        ]);

        $this->putJson("/api/v1/accounting/budgets/{$budget->id}", [
            'name' => 'Updated Budget Name',
        ])
            ->assertStatus(200);
    });

    test('DELETE /api/v1/accounting/budgets/{id} deletes budget and returns', function () {
        $budget = Budget::factory()->create(['created_by' => $this->user->id]);

        $this->deleteJson("/api/v1/accounting/budgets/{$budget->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('acc_budgets', ['id' => $budget->id]);
    });

    test('POST /api/v1/accounting/budgets/{id}/lines adds a line', function () {
        $budget = Budget::factory()->create([
            'created_by' => $this->user->id,
            'total_budget' => 0,
        ]);

        $this->postJson("/api/v1/accounting/budgets/{$budget->id}/lines", [
            'category' => 'software',
            'description' => 'SaaS subscriptions',
            'budgeted_amount' => 12000,
            'period' => 'annual',
        ])
            ->assertStatus(201)
            ->assertJsonFragment(['category' => 'software']);
    });

    test('POST /api/v1/accounting/budgets/lines/{line}/spend records spending', function () {
        $budget = Budget::factory()->create([
            'created_by' => $this->user->id,
            'total_spent' => 0,
        ]);

        $line = BudgetLine::factory()->create([
            'budget_id' => $budget->id,
            'spent_amount' => 0,
            'account_id' => null,
        ]);

        $this->postJson("/api/v1/accounting/budgets/lines/{$line->id}/spend", [
            'amount' => 5000,
        ])
            ->assertStatus(200);

        $line->refresh();
        expect((float) $line->spent_amount)->toBe(5000.0);
    });

    test('POST /api/v1/accounting/budgets/{id}/approve approves the budget', function () {
        $budget = Budget::factory()->create([
            'created_by' => $this->user->id,
            'status' => 'draft',
        ]);

        $this->postJson("/api/v1/accounting/budgets/{$budget->id}/approve")
            ->assertStatus(200)
            ->assertJsonFragment(['status' => 'approved']);
    });

    test('GET /api/v1/accounting/budgets/{id}/variance returns variance report', function () {
        $budget = Budget::factory()->create(['created_by' => $this->user->id]);
        BudgetLine::factory()->create([
            'budget_id' => $budget->id,
            'category' => 'travel',
            'budgeted_amount' => 10000,
            'spent_amount' => 7000,
            'account_id' => null,
        ]);

        $this->getJson("/api/v1/accounting/budgets/{$budget->id}/variance")
            ->assertStatus(200);
    });

    test('GET /api/v1/accounting/budgets/summary returns summary data', function () {
        Budget::factory()->create([
            'created_by' => $this->user->id,
            'status' => 'active',
            'total_budget' => 100000,
            'total_spent' => 30000,
        ]);

        $this->getJson('/api/v1/accounting/budgets/summary')
            ->assertStatus(200)
            ->assertJsonStructure(['total_budgeted', 'total_spent', 'overall_utilization']);
    });

    test('GET /api/v1/accounting/budgets/over-budget returns over-budget lines', function () {
        $budget = Budget::factory()->create([
            'created_by' => $this->user->id,
            'status' => 'active',
            'total_budget' => 10000,
            'total_spent' => 15000,
        ]);

        BudgetLine::factory()->create([
            'budget_id' => $budget->id,
            'budgeted_amount' => 5000,
            'spent_amount' => 8000,
            'account_id' => null,
        ]);

        $this->getJson('/api/v1/accounting/budgets/over-budget')
            ->assertStatus(200);
    });

    test('GET /api/v1/accounting/budgets/department-breakdown returns department data', function () {
        Budget::factory()->create([
            'created_by' => $this->user->id,
            'status' => 'active',
            'department' => 'HR',
            'total_budget' => 50000,
            'total_spent' => 20000,
        ]);

        $this->getJson('/api/v1/accounting/budgets/department-breakdown')
            ->assertStatus(200);
    });

    test('POST /api/v1/accounting/budgets/{id}/clone clones budget to new year', function () {
        $budget = Budget::factory()->create([
            'created_by' => $this->user->id,
            'fiscal_year' => 2026,
        ]);

        $this->postJson("/api/v1/accounting/budgets/{$budget->id}/clone", [
            'new_year' => 2027,
        ])
            ->assertStatus(201)
            ->assertJsonFragment(['fiscal_year' => 2027, 'status' => 'draft']);
    });

    // ─── Authentication Tests ──────────────────────────────────────────────────

    test('unauthenticated requests return 401', function () {
        \Illuminate\Support\Facades\Auth::forgetGuards();
        $this->getJson('/api/v1/accounting/budgets')
            ->assertStatus(401);
    });
});
