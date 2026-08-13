<?php

declare(strict_types=1);

use Modules\Accounting\Models\CashFlowLine;
use Modules\Accounting\Models\TreasuryForecast;
use Modules\Accounting\Models\TreasuryScenario;
use Modules\Accounting\Services\TreasuryService;


// ─── TreasuryForecast model methods ──────────────────────────────────────────

it('TreasuryForecast isDraft returns true for draft status', function () {
    $forecast = TreasuryForecast::factory()->create(['status' => 'draft']);

    expect($forecast->isDraft())->toBeTrue();
});

it('TreasuryForecast isDraft returns false for active status', function () {
    $forecast = TreasuryForecast::factory()->create(['status' => 'active']);

    expect($forecast->isDraft())->toBeFalse();
});

it('TreasuryForecast netCashFlow returns inflows minus outflows', function () {
    $forecast = TreasuryForecast::factory()->create([
        'total_inflows' => 10000,
        'total_outflows' => 7000,
    ]);

    expect($forecast->netCashFlow())->toBe(3000.0);
});

it('TreasuryForecast isPositive returns true when closing balance >= 0', function () {
    $forecast = TreasuryForecast::factory()->create(['closing_balance' => 5000]);

    expect($forecast->isPositive())->toBeTrue();
});

it('TreasuryForecast isPositive returns false when closing balance < 0', function () {
    $forecast = TreasuryForecast::factory()->create(['closing_balance' => -100]);

    expect($forecast->isPositive())->toBeFalse();
});

it('TreasuryForecast recompute sums lines correctly', function () {
    $forecast = TreasuryForecast::factory()->create([
        'opening_balance' => 5000,
        'total_inflows' => 0,
        'total_outflows' => 0,
        'closing_balance' => 5000,
    ]);

    CashFlowLine::factory()->create([
        'forecast_id' => $forecast->id,
        'flow_type' => 'inflow',
        'amount' => 3000,
        'category' => 'sales_revenue',
        'expected_date' => now()->toDateString(),
    ]);

    CashFlowLine::factory()->create([
        'forecast_id' => $forecast->id,
        'flow_type' => 'outflow',
        'amount' => 1500,
        'category' => 'salaries',
        'expected_date' => now()->toDateString(),
    ]);

    $forecast->recompute();
    $forecast->refresh();

    expect((float) $forecast->total_inflows)->toBe(3000.0)
        ->and((float) $forecast->total_outflows)->toBe(1500.0)
        ->and((float) $forecast->closing_balance)->toBe(6500.0);
});

it('TreasuryForecast activate changes status to active', function () {
    $forecast = TreasuryForecast::factory()->create(['status' => 'draft']);

    $forecast->activate();

    expect($forecast->fresh()->status)->toBe('active');
});

it('TreasuryForecast coverageRatio returns inflows divided by outflows', function () {
    $forecast = TreasuryForecast::factory()->create([
        'total_inflows' => 10000,
        'total_outflows' => 5000,
    ]);

    expect($forecast->coverageRatio())->toBe(2.0);
});

it('TreasuryForecast coverageRatio returns 0 when outflows are zero', function () {
    $forecast = TreasuryForecast::factory()->create([
        'total_inflows' => 10000,
        'total_outflows' => 0,
    ]);

    expect($forecast->coverageRatio())->toBe(0.0);
});

// ─── CashFlowLine model methods ───────────────────────────────────────────────

it('CashFlowLine isInflow returns true for inflow type', function () {
    $line = CashFlowLine::factory()->create(['flow_type' => 'inflow']);

    expect($line->isInflow())->toBeTrue();
    expect($line->isOutflow())->toBeFalse();
});

it('CashFlowLine isOutflow returns true for outflow type', function () {
    $line = CashFlowLine::factory()->outflow()->create();

    expect($line->isOutflow())->toBeTrue();
    expect($line->isInflow())->toBeFalse();
});

it('CashFlowLine weightedAmount multiplies amount by probability', function () {
    $line = CashFlowLine::factory()->create([
        'amount' => 10000,
        'probability' => 75,
    ]);

    expect($line->weightedAmount())->toBe(7500.0);
});

it('CashFlowLine isRealized returns false when actual_amount is null', function () {
    $line = CashFlowLine::factory()->create(['actual_amount' => null]);

    expect($line->isRealized())->toBeFalse();
});

it('CashFlowLine isRealized returns true when actual_amount is set', function () {
    $line = CashFlowLine::factory()->create(['actual_amount' => 9500]);

    expect($line->isRealized())->toBeTrue();
});

it('CashFlowLine variance returns actual minus amount when realized', function () {
    $line = CashFlowLine::factory()->create([
        'amount' => 10000,
        'actual_amount' => 9500,
    ]);

    expect($line->variance())->toBe(-500.0);
});

it('CashFlowLine variance returns 0 when not realized', function () {
    $line = CashFlowLine::factory()->create(['actual_amount' => null]);

    expect($line->variance())->toBe(0.0);
});

// ─── TreasuryScenario model methods ──────────────────────────────────────────

it('TreasuryScenario isBase returns true for base type', function () {
    $scenario = TreasuryScenario::factory()->create(['type' => 'base']);

    expect($scenario->isBase())->toBeTrue();
    expect($scenario->isOptimistic())->toBeFalse();
    expect($scenario->isPessimistic())->toBeFalse();
});

it('TreasuryScenario isOptimistic returns true for optimistic type', function () {
    $scenario = TreasuryScenario::factory()->optimistic()->create();

    expect($scenario->isOptimistic())->toBeTrue();
});

it('TreasuryScenario isPessimistic returns true for pessimistic type', function () {
    $scenario = TreasuryScenario::factory()->pessimistic()->create();

    expect($scenario->isPessimistic())->toBeTrue();
});

it('TreasuryScenario computeBalance applies adjustment factor correctly', function () {
    $forecast = TreasuryForecast::factory()->create([
        'opening_balance' => 5000,
        'total_inflows' => 10000,
        'total_outflows' => 6000,
    ]);

    $scenario = TreasuryScenario::factory()->create([
        'forecast_id' => $forecast->id,
        'type' => 'optimistic',
        'adjustment_factor' => 1.2,
    ]);

    // (10000 - 6000) * 1.2 + 5000 = 4000 * 1.2 + 5000 = 4800 + 5000 = 9800
    expect($scenario->computeBalance($forecast))->toBe(9800.0);
});

// ─── TreasuryService ─────────────────────────────────────────────────────────

it('TreasuryService createForecast creates a forecast', function () {
    actingAsUser('admin');

    $service = app(TreasuryService::class);

    $forecast = $service->createForecast([
        'name' => 'Q1 Forecast',
        'period_start' => now()->startOfMonth()->toDateString(),
        'period_end' => now()->endOfMonth()->toDateString(),
        'currency' => 'USD',
    ]);

    expect($forecast)->toBeInstanceOf(TreasuryForecast::class)
        ->and($forecast->name)->toBe('Q1 Forecast');
});

it('TreasuryService createForecast creates lines when provided', function () {
    actingAsUser('admin');

    $service = app(TreasuryService::class);

    $forecast = $service->createForecast([
        'name' => 'Test Forecast',
        'period_start' => now()->startOfMonth()->toDateString(),
        'period_end' => now()->endOfMonth()->toDateString(),
        'opening_balance' => 5000,
    ], [
        [
            'category' => 'sales_revenue',
            'flow_type' => 'inflow',
            'amount' => 8000,
            'expected_date' => now()->toDateString(),
        ],
    ]);

    expect($forecast->lines()->count())->toBe(1)
        ->and((float) $forecast->fresh()->total_inflows)->toBe(8000.0);
});

it('TreasuryService addLine adds a line and recomputes', function () {
    actingAsUser('admin');

    $service = app(TreasuryService::class);
    $forecast = TreasuryForecast::factory()->create(['opening_balance' => 10000]);

    $line = $service->addLine($forecast, [
        'category' => 'salaries',
        'flow_type' => 'outflow',
        'amount' => 3000,
        'expected_date' => now()->toDateString(),
    ]);

    expect($line)->toBeInstanceOf(CashFlowLine::class)
        ->and((float) $forecast->fresh()->total_outflows)->toBe(3000.0);
});

it('TreasuryService removeLine removes a line and recomputes', function () {
    actingAsUser('admin');

    $service = app(TreasuryService::class);
    $forecast = TreasuryForecast::factory()->create(['opening_balance' => 10000]);
    $line = $service->addLine($forecast, [
        'category' => 'sales_revenue',
        'flow_type' => 'inflow',
        'amount' => 5000,
        'expected_date' => now()->toDateString(),
    ]);

    $service->removeLine($line);

    expect($forecast->fresh()->lines()->count())->toBe(0)
        ->and((float) $forecast->fresh()->total_inflows)->toBe(0.0);
});

it('TreasuryService recompute updates forecast totals', function () {
    actingAsUser('admin');

    $service = app(TreasuryService::class);
    $forecast = TreasuryForecast::factory()->create([
        'opening_balance' => 2000,
        'total_inflows' => 0,
        'total_outflows' => 0,
    ]);

    CashFlowLine::factory()->create([
        'forecast_id' => $forecast->id,
        'flow_type' => 'inflow',
        'amount' => 5000,
        'category' => 'sales_revenue',
        'expected_date' => now()->toDateString(),
    ]);

    $updated = $service->recompute($forecast);

    expect((float) $updated->total_inflows)->toBe(5000.0)
        ->and((float) $updated->closing_balance)->toBe(7000.0);
});

it('TreasuryService createScenario creates a scenario with computed balance', function () {
    actingAsUser('admin');

    $service = app(TreasuryService::class);
    $forecast = TreasuryForecast::factory()->create([
        'opening_balance' => 1000,
        'total_inflows' => 5000,
        'total_outflows' => 3000,
    ]);

    $scenario = $service->createScenario($forecast, [
        'name' => 'Base',
        'type' => 'base',
        'adjustment_factor' => 1.0,
    ]);

    expect($scenario)->toBeInstanceOf(TreasuryScenario::class)
        ->and((float) $scenario->scenario_balance)->toBe(3000.0); // (5000-3000)*1.0 + 1000
});

it('TreasuryService runScenarioAnalysis creates all 3 scenario types', function () {
    actingAsUser('admin');

    $service = app(TreasuryService::class);
    $forecast = TreasuryForecast::factory()->create([
        'opening_balance' => 1000,
        'total_inflows' => 5000,
        'total_outflows' => 3000,
    ]);

    $scenarios = $service->runScenarioAnalysis($forecast);

    expect($scenarios)->toHaveKeys(['base', 'optimistic', 'pessimistic'])
        ->and($scenarios['base']->type)->toBe('base')
        ->and($scenarios['optimistic']->type)->toBe('optimistic')
        ->and($scenarios['pessimistic']->type)->toBe('pessimistic');
});

it('TreasuryService runScenarioAnalysis computes correct balances', function () {
    actingAsUser('admin');

    $service = app(TreasuryService::class);
    $forecast = TreasuryForecast::factory()->create([
        'opening_balance' => 1000,
        'total_inflows' => 5000,
        'total_outflows' => 3000,
    ]);

    $scenarios = $service->runScenarioAnalysis($forecast);

    // base: (5000-3000)*1.0 + 1000 = 3000
    // optimistic: (5000-3000)*1.2 + 1000 = 3400
    // pessimistic: (5000-3000)*0.8 + 1000 = 2600
    expect((float) $scenarios['base']->scenario_balance)->toBe(3000.0)
        ->and((float) $scenarios['optimistic']->scenario_balance)->toBe(3400.0)
        ->and((float) $scenarios['pessimistic']->scenario_balance)->toBe(2600.0);
});

it('TreasuryService getMonthlyProjection returns correct month count', function () {
    actingAsUser('admin');

    $service = app(TreasuryService::class);
    $projection = $service->getMonthlyProjection(0.0, 6);

    expect($projection)->toHaveCount(6)
        ->and($projection[0])->toHaveKeys(['month', 'inflows', 'outflows', 'net', 'cumulative_balance']);
});

it('TreasuryService getForecastsInRange returns overlapping forecasts', function () {
    actingAsUser('admin');

    $service = app(TreasuryService::class);

    TreasuryForecast::factory()->create([
        'period_start' => now()->startOfMonth()->toDateString(),
        'period_end' => now()->endOfMonth()->toDateString(),
    ]);

    TreasuryForecast::factory()->create([
        'period_start' => now()->addMonths(3)->startOfMonth()->toDateString(),
        'period_end' => now()->addMonths(3)->endOfMonth()->toDateString(),
    ]);

    $forecasts = $service->getForecastsInRange(
        now()->startOfMonth(),
        now()->endOfMonth()
    );

    expect($forecasts)->toHaveCount(1);
});

it('TreasuryService getDashboard returns correct keys', function () {
    actingAsUser('admin');

    $service = app(TreasuryService::class);
    $dashboard = $service->getDashboard();

    expect($dashboard)->toHaveKeys([
        'current_balance',
        'monthly_burn_rate',
        'runway_months',
        'total_forecasted_inflows',
        'total_forecasted_outflows',
    ]);
});

it('TreasuryService realizeLine sets actual amount', function () {
    actingAsUser('admin');

    $service = app(TreasuryService::class);
    $line = CashFlowLine::factory()->create(['actual_amount' => null]);

    $realized = $service->realizeLine($line, 9500.0);

    expect($realized->isRealized())->toBeTrue()
        ->and((float) $realized->actual_amount)->toBe(9500.0);
});

// ─── API Tests ────────────────────────────────────────────────────────────────

it('GET /api/v1/accounting/treasury-planning requires authentication', function () {
    auth()->logout();

    $this->getJson('/api/v1/accounting/treasury-planning')
        ->assertStatus(401);
});

it('GET /api/v1/accounting/treasury-planning returns paginated forecasts', function () {
    actingAsUser('admin');

    TreasuryForecast::factory()->count(3)->create();

    $this->getJson('/api/v1/accounting/treasury-planning')
        ->assertOk()
        ->assertJsonStructure(['data', 'total']);
});

it('POST /api/v1/accounting/treasury-planning creates a forecast', function () {
    actingAsUser('admin');

    $this->postJson('/api/v1/accounting/treasury-planning', [
        'name' => 'Q1 2026 Forecast',
        'period_start' => '2026-01-01',
        'period_end' => '2026-03-31',
        'currency' => 'USD',
    ])->assertStatus(201)
        ->assertJsonFragment(['name' => 'Q1 2026 Forecast']);
});

it('GET /api/v1/accounting/treasury-planning/{id} returns forecast with lines and scenarios', function () {
    actingAsUser('admin');

    $forecast = TreasuryForecast::factory()->create();

    $this->getJson("/api/v1/accounting/treasury-planning/{$forecast->id}")
        ->assertOk()
        ->assertJsonFragment(['id' => $forecast->id])
        ->assertJsonStructure(['id', 'name', 'lines', 'scenarios']);
});

it('PUT /api/v1/accounting/treasury-planning/{id} updates a forecast', function () {
    actingAsUser('admin');

    $forecast = TreasuryForecast::factory()->create(['name' => 'Old Name']);

    $this->putJson("/api/v1/accounting/treasury-planning/{$forecast->id}", [
        'name' => 'Updated Name',
    ])->assertOk()
        ->assertJsonFragment(['name' => 'Updated Name']);
});

it('DELETE /api/v1/accounting/treasury-planning/{id} deletes a forecast', function () {
    actingAsUser('admin');

    $forecast = TreasuryForecast::factory()->create();

    $this->deleteJson("/api/v1/accounting/treasury-planning/{$forecast->id}")
        ->assertNoContent();

    expect(TreasuryForecast::find($forecast->id))->toBeNull();
});

it('POST /api/v1/accounting/treasury-planning/{id}/lines adds a line', function () {
    actingAsUser('admin');

    $forecast = TreasuryForecast::factory()->create();

    $this->postJson("/api/v1/accounting/treasury-planning/{$forecast->id}/lines", [
        'category' => 'sales_revenue',
        'flow_type' => 'inflow',
        'amount' => 5000,
        'expected_date' => now()->toDateString(),
    ])->assertStatus(201)
        ->assertJsonFragment(['flow_type' => 'inflow']);
});

it('DELETE /api/v1/accounting/treasury-planning/{id}/lines/{lineId} removes a line', function () {
    actingAsUser('admin');

    $forecast = TreasuryForecast::factory()->create();
    $line = CashFlowLine::factory()->create(['forecast_id' => $forecast->id]);

    $this->deleteJson("/api/v1/accounting/treasury-planning/{$forecast->id}/lines/{$line->id}")
        ->assertNoContent();

    expect(CashFlowLine::find($line->id))->toBeNull();
});

it('POST /api/v1/accounting/treasury-planning/{id}/recompute recomputes totals', function () {
    actingAsUser('admin');

    $forecast = TreasuryForecast::factory()->create(['opening_balance' => 1000]);
    CashFlowLine::factory()->create([
        'forecast_id' => $forecast->id,
        'flow_type' => 'inflow',
        'amount' => 3000,
        'category' => 'sales_revenue',
        'expected_date' => now()->toDateString(),
    ]);

    $this->postJson("/api/v1/accounting/treasury-planning/{$forecast->id}/recompute")
        ->assertOk()
        ->assertJsonFragment(['total_inflows' => '3000.0000']);
});

it('GET /api/v1/accounting/treasury-planning/{id}/scenarios returns scenarios', function () {
    actingAsUser('admin');

    $forecast = TreasuryForecast::factory()->create();
    TreasuryScenario::factory()->create(['forecast_id' => $forecast->id]);

    $this->getJson("/api/v1/accounting/treasury-planning/{$forecast->id}/scenarios")
        ->assertOk()
        ->assertJsonStructure([['id', 'type', 'adjustment_factor']]);
});

it('POST /api/v1/accounting/treasury-planning/{id}/scenarios creates a scenario', function () {
    actingAsUser('admin');

    $forecast = TreasuryForecast::factory()->create();

    $this->postJson("/api/v1/accounting/treasury-planning/{$forecast->id}/scenarios", [
        'name' => 'Optimistic',
        'type' => 'optimistic',
        'adjustment_factor' => 1.2,
    ])->assertStatus(201)
        ->assertJsonFragment(['type' => 'optimistic']);
});

it('POST /api/v1/accounting/treasury-planning/{id}/scenario-analysis runs analysis', function () {
    actingAsUser('admin');

    $forecast = TreasuryForecast::factory()->create([
        'opening_balance' => 1000,
        'total_inflows' => 5000,
        'total_outflows' => 3000,
    ]);

    $this->postJson("/api/v1/accounting/treasury-planning/{$forecast->id}/scenario-analysis")
        ->assertOk()
        ->assertJsonStructure(['base', 'optimistic', 'pessimistic']);
});

it('GET /api/v1/accounting/treasury-planning/projection returns monthly projection', function () {
    actingAsUser('admin');

    $this->getJson('/api/v1/accounting/treasury-planning/projection?months=6')
        ->assertOk()
        ->assertJsonStructure(['projection'])
        ->assertJsonCount(6, 'projection');
});

it('GET /api/v1/accounting/treasury-planning/dashboard returns dashboard data', function () {
    actingAsUser('admin');

    $this->getJson('/api/v1/accounting/treasury-planning/dashboard')
        ->assertOk()
        ->assertJsonStructure([
            'current_balance',
            'monthly_burn_rate',
            'runway_months',
            'total_forecasted_inflows',
            'total_forecasted_outflows',
        ]);
});

it('POST /api/v1/accounting/treasury-planning/lines/{line}/realize marks line as realized', function () {
    actingAsUser('admin');

    $line = CashFlowLine::factory()->create(['actual_amount' => null]);

    $this->postJson("/api/v1/accounting/treasury-planning/lines/{$line->id}/realize", [
        'actual_amount' => 9500,
    ])->assertOk()
        ->assertJsonFragment(['actual_amount' => '9500.0000']);
});
