<?php

use Carbon\Carbon;
use Modules\Accounting\Models\Budget;

describe('Budget API', function () {
    beforeEach(function () {
        $this->user = actingAsUser('admin');
    });

    test('can create a budget', function () {
        $year = (int) date('Y');
        $startDate = Carbon::createFromDate($year, 1, 1)->format('Y-m-d');
        $endDate = Carbon::createFromDate($year, 12, 31)->format('Y-m-d');

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/accounting/budgets', [
                'name' => 'Marketing Budget',
                'fiscal_year' => $year,
                'fiscal_year_start' => $startDate,
                'fiscal_year_end' => $endDate,
                'total_revenue_budget' => 100000,
                'total_expense_budget' => 50000,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Marketing Budget');

        $this->assertDatabaseHas('acc_budgets', ['name' => 'Marketing Budget']);
    });

    test('can get budgets by year', function () {
        $year = (int) date('Y');
        Budget::factory()->create(['fiscal_year' => $year]);
        Budget::factory()->create(['fiscal_year' => $year - 1]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/accounting/budgets?fiscal_year={$year}");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    });

    test('can identify over budget items', function () {
        Budget::factory()->create([
            'fiscal_year' => (int) date('Y'),
            'total_revenue_budget' => 1000,
            'total_expense_budget' => 1500,
        ]);
        Budget::factory()->create([
            'fiscal_year' => (int) date('Y'),
            'total_revenue_budget' => 5000,
            'total_expense_budget' => 4000,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/accounting/budgets/over-budget?fiscal_year='.date('Y'));

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    });
});
