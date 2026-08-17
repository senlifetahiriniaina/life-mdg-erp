<?php

declare(strict_types=1);

use Modules\Accounting\Models\Budget;
use Modules\Accounting\Models\FixedAsset;
use Modules\Accounting\Models\GLAccount;

describe('Chantier 8.1b — newly wired Accounting endpoints', function () {
    beforeEach(function () {
        $this->user = actingAsUser('admin');
    });

    test('can create, approve and record an asset impairment', function () {
        $asset = FixedAsset::factory()->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/accounting/asset-impairments', [
                'fixed_asset_id' => $asset->id,
                'impairment_date' => now()->toDateString(),
                'original_cost' => 10000,
                'accumulated_depreciation_before' => 2000,
                'book_value_before' => 8000,
                'fair_value' => 6000,
                'impairment_reason' => 'Market decline',
            ]);

        $response->assertCreated();
        $impairmentId = $response->json('id');
        $this->assertDatabaseHas('asset_impairments', ['id' => $impairmentId, 'status' => 'draft']);

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/accounting/asset-impairments/{$impairmentId}/approve")
            ->assertOk();
        $this->assertDatabaseHas('asset_impairments', ['id' => $impairmentId, 'status' => 'approved']);
    });

    test('can create a depreciation policy', function () {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/accounting/depreciation-policies', [
                'policy_name' => 'Vehicles 5yr',
                'asset_category' => 'Vehicles',
                'depreciation_method' => 'straight_line',
                'default_useful_life_years' => 5,
                'default_residual_percentage' => 10,
                'effective_from' => now()->toDateString(),
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('depreciation_policies', ['policy_name' => 'Vehicles 5yr']);
    });

    test('can create and record a depreciation schedule against real GL account tables', function () {
        $asset = FixedAsset::factory()->create();
        $expenseAccount = GLAccount::factory()->create();
        $accumulatedAccount = GLAccount::factory()->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/accounting/depreciation-schedules', [
                'fixed_asset_id' => $asset->id,
                'depreciation_method' => 'straight_line',
                'useful_life_years' => 5,
                'residual_value' => 500,
                'depreciation_start_date' => now()->toDateString(),
                'annual_depreciation_amount' => 1000,
                'depreciation_expense_account_id' => $expenseAccount->id,
                'accumulated_depreciation_account_id' => $accumulatedAccount->id,
            ]);

        $response->assertCreated();
        $scheduleId = $response->json('id');
        $this->assertDatabaseHas('depreciation_schedules', ['id' => $scheduleId, 'status' => 'active']);

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/accounting/depreciation-schedules/{$scheduleId}/record", [
                'period_date' => now()->toDateString(),
            ])
            ->assertCreated();
    });

    test('can create and clear an intercompany clearance', function () {
        $sending = \App\Models\Company::factory()->create();
        $receiving = \App\Models\Company::factory()->create();
        $sendingGl = GLAccount::factory()->create();
        $receivingGl = GLAccount::factory()->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/accounting/intercompany-clearances', [
                'sending_company_id' => $sending->id,
                'receiving_company_id' => $receiving->id,
                'transaction_date' => now()->toDateString(),
                'transaction_type' => 'loan',
                'amount' => 5000,
                'currency' => 'XOF',
                'sending_gl_account_id' => $sendingGl->id,
                'receiving_gl_account_id' => $receivingGl->id,
            ]);

        $response->assertCreated();
        $clearanceId = $response->json('id');

        $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/accounting/intercompany-clearances/{$clearanceId}/clear")
            ->assertOk();
        $this->assertDatabaseHas('intercompany_clearances', ['id' => $clearanceId, 'status' => 'cleared']);
    });

    test('can analyze budget variance', function () {
        $budget = Budget::factory()->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/accounting/budget-variance/analyze?budget_id=' . $budget->id);

        $response->assertOk()
            ->assertJsonStructure(['budget_amount', 'actual_amount', 'variance_amount']);
    });

    test('can get cost engine summary', function () {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/accounting/cost-engine/summary');

        $response->assertOk()
            ->assertJsonStructure(['CAPEX', 'OPEX', 'FINEX', 'RISKEX', 'total']);
    });

    test('can create, simulate and approve a budget scenario', function () {
        $budget = Budget::factory()->create();

        $create = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/accounting/scenario-planning', [
                'name' => 'Optimistic +10%',
                'type' => 'revenue',
                'parameters' => [
                    'base_budget_id' => $budget->id,
                    'adjustment_type' => 'percentage',
                    'revenue_adjustment' => 0.10,
                ],
            ]);
        $create->assertCreated();
        $scenarioId = $create->json('id');

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/accounting/scenario-planning/simulate', ['scenario_id' => $scenarioId])
            ->assertOk()
            ->assertJsonStructure(['projected_revenue', 'projected_expenses', 'projected_profit']);

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/accounting/scenario-planning/approve', ['scenario_id' => $scenarioId])
            ->assertOk();
        $this->assertDatabaseHas('acc_budget_scenarios', ['id' => $scenarioId, 'status' => 'approved']);
    });

    test('scenario planning index lists created scenarios', function () {
        $budget = Budget::factory()->create();
        \Modules\Accounting\Models\BudgetScenario::factory()->create(['base_budget_id' => $budget->id, 'name' => 'Listed scenario']);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/accounting/scenario-planning');

        $response->assertOk();
        expect(collect($response->json())->pluck('name'))->toContain('Listed scenario');
    });
});
