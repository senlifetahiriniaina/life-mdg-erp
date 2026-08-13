<?php

use Modules\Accounting\Models\CashFlowForecast;
use Modules\Accounting\Models\TreasuryAlert;
use Modules\Accounting\Services\CashFlowForecastService;

describe('Cash Flow Forecasting (Treasury Planning)', function () {
    beforeEach(function () {
        $this->user = actingAsUser('admin');
        $this->service = app(CashFlowForecastService::class);
    });

    // ─── Forecast generation ──────────────────────────────────────────────────

    test('can generate a 30-day forecast', function () {
        $forecast = $this->service->generate([
            'name' => 'Test Forecast',
            'base_date' => '2026-01-01',
            'horizon' => '30d',
            'opening_balance' => 10000,
            'scenario' => 'base',
        ], $this->user->id);

        expect($forecast)->toBeInstanceOf(CashFlowForecast::class);
        expect($forecast->horizon)->toBe('30d');
        expect($forecast->end_date->toDateString())->toBe('2026-01-31');
        expect((float) $forecast->opening_balance)->toEqual(10000.0);
    });

    test('can generate a 60-day forecast', function () {
        $forecast = $this->service->generate([
            'name' => '60-day Test',
            'base_date' => '2026-01-01',
            'horizon' => '60d',
            'opening_balance' => 5000,
        ], $this->user->id);

        expect($forecast->end_date->toDateString())->toBe('2026-03-02');
    });

    test('can generate a 90-day forecast', function () {
        $forecast = $this->service->generate([
            'name' => '90-day Test',
            'base_date' => '2026-01-01',
            'horizon' => '90d',
            'opening_balance' => 20000,
        ], $this->user->id);

        expect($forecast->end_date->toDateString())->toBe('2026-04-01');
        expect($forecast->status)->toBe('draft');
    });

    test('forecast has correct model attributes', function () {
        $forecast = $this->service->generate([
            'name' => 'Threshold Test',
            'base_date' => today()->toDateString(),
            'horizon' => '30d',
            'opening_balance' => 15000,
            'minimum_balance_threshold' => 5000,
        ], $this->user->id);

        expect($forecast->minimum_balance_threshold)->not->toBeNull();
        expect((float) $forecast->minimum_balance_threshold)->toEqual(5000.0);
    });

    // ─── Items ────────────────────────────────────────────────────────────────

    test('can add a manual inflow item', function () {
        $forecast = CashFlowForecast::factory()->create(['created_by' => $this->user->id]);

        $item = $this->service->addItem($forecast, [
            'date' => today()->toDateString(),
            'category' => 'client_payment',
            'type' => 'inflow',
            'source' => 'Acme Corp',
            'description' => 'Q1 retainer',
            'amount' => 5000,
            'probability' => 90,
        ]);

        expect($item->type)->toBe('inflow');
        expect((float) $item->amount)->toEqual(5000.0);
        expect((float) $item->weighted_amount)->toEqual(4500.0);
    });

    test('can add a manual outflow item', function () {
        $forecast = CashFlowForecast::factory()->create(['created_by' => $this->user->id]);

        $item = $this->service->addItem($forecast, [
            'date' => today()->toDateString(),
            'category' => 'rent',
            'type' => 'outflow',
            'source' => 'Office Lease',
            'amount' => 3000,
        ]);

        expect($item->type)->toBe('outflow');
        expect((float) $item->weighted_amount)->toEqual(3000.0);
    });

    test('adding item triggers recalculation of projected closing balance', function () {
        $forecast = CashFlowForecast::factory()->create([
            'created_by' => $this->user->id,
            'opening_balance' => 10000,
            'projected_closing_balance' => 10000,
        ]);

        $this->service->addItem($forecast, [
            'date' => today()->toDateString(),
            'category' => 'sales',
            'type' => 'inflow',
            'source' => 'Product Sale',
            'amount' => 2500,
        ]);

        $forecast->refresh();
        expect((float) $forecast->projected_closing_balance)->toEqual(12500.0);
    });

    // ─── Timeline ─────────────────────────────────────────────────────────────

    test('timeline builds running balance per day', function () {
        $forecast = CashFlowForecast::factory()->create([
            'created_by' => $this->user->id,
            'opening_balance' => 10000,
            'projected_closing_balance' => 10000,
        ]);

        $this->service->addItem($forecast, [
            'date' => $forecast->base_date->toDateString(), 'category' => 'sales',
            'type' => 'inflow', 'source' => 'A', 'amount' => 1000,
        ]);
        $this->service->addItem($forecast, [
            'date' => $forecast->base_date->toDateString(), 'category' => 'rent',
            'type' => 'outflow', 'source' => 'B', 'amount' => 400,
        ]);

        $timeline = $this->service->buildTimeline($forecast);

        expect($timeline)->toBeArray();
        $day0 = collect($timeline)->firstWhere('date', $forecast->base_date->toDateString());
        expect($day0)->not->toBeNull();
        expect($day0['inflows'])->toEqual(1000.0);
        expect($day0['outflows'])->toEqual(400.0);
        expect($day0['running_balance'])->toEqual(10600.0);
    });

    test('timeline marks days below threshold', function () {
        $forecast = CashFlowForecast::factory()->create([
            'created_by' => $this->user->id,
            'opening_balance' => 1000,
            'minimum_balance_threshold' => 5000,
            'projected_closing_balance' => 1000,
        ]);

        $timeline = $this->service->buildTimeline($forecast);

        // With opening balance 1000 < threshold 5000, first day should flag
        $first = $timeline[0];
        expect($first['below_threshold'])->toBeTrue();
    });

    // ─── Summary KPIs ─────────────────────────────────────────────────────────

    test('summary returns expected KPI structure', function () {
        $forecast = CashFlowForecast::factory()->create([
            'created_by' => $this->user->id,
            'opening_balance' => 10000,
            'projected_closing_balance' => 10000,
        ]);

        $this->service->addItem($forecast, [
            'date' => $forecast->base_date->toDateString(), 'category' => 'ar',
            'type' => 'inflow', 'source' => 'Invoice', 'amount' => 5000,
        ]);

        $summary = $this->service->buildSummary($forecast);

        expect($summary)->toHaveKeys([
            'opening_balance', 'projected_closing_balance', 'total_inflows',
            'total_outflows', 'net_cash_flow', 'runway_days', 'is_positive', 'period',
        ]);
        expect($summary['total_inflows'])->toEqual(5000.0);
        expect($summary['total_outflows'])->toEqual(0.0);
        expect($summary['net_cash_flow'])->toEqual(5000.0);
    });

    test('isBreachingMinimum returns true when projected below threshold', function () {
        $forecast = CashFlowForecast::factory()->create([
            'created_by' => $this->user->id,
            'opening_balance' => 1000,
            'projected_closing_balance' => 800,
            'minimum_balance_threshold' => 5000,
        ]);

        expect($forecast->isBreachingMinimum())->toBeTrue();
    });

    test('isBreachingMinimum returns false when above threshold', function () {
        $forecast = CashFlowForecast::factory()->create([
            'created_by' => $this->user->id,
            'projected_closing_balance' => 15000,
            'minimum_balance_threshold' => 5000,
        ]);

        expect($forecast->isBreachingMinimum())->toBeFalse();
    });

    // ─── Treasury Alerts ──────────────────────────────────────────────────────

    test('treasury alert triggers for low balance', function () {
        $alert = TreasuryAlert::factory()->create([
            'type' => 'low_balance',
            'threshold_amount' => 10000,
            'days_lookahead' => 30,
            'is_active' => true,
            'created_by' => $this->user->id,
        ]);

        expect($alert->isTriggered(5000, 7))->toBeTrue();
        expect($alert->isTriggered(15000, 7))->toBeFalse();
    });

    test('treasury alert does not trigger when inactive', function () {
        $alert = TreasuryAlert::factory()->create([
            'type' => 'low_balance',
            'threshold_amount' => 10000,
            'is_active' => false,
            'created_by' => $this->user->id,
        ]);

        expect($alert->isTriggered(5000, 7))->toBeFalse();
    });

    test('treasury alert does not trigger beyond lookahead window', function () {
        $alert = TreasuryAlert::factory()->create([
            'type' => 'low_balance',
            'threshold_amount' => 10000,
            'days_lookahead' => 7,
            'is_active' => true,
            'created_by' => $this->user->id,
        ]);

        // 30 days out — outside the 7-day lookahead window
        expect($alert->isTriggered(5000, 30))->toBeFalse();
    });

    // ─── API Endpoints ────────────────────────────────────────────────────────

    test('GET /api/v1/accounting/treasury/forecasts returns list', function () {
        CashFlowForecast::factory()->count(3)->create(['created_by' => $this->user->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/accounting/treasury/forecasts');

        expect($response->status())->toBe(200);
        expect($response->json('data'))->toHaveCount(3);
    });

    test('POST /api/v1/accounting/treasury/forecasts creates forecast', function () {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/accounting/treasury/forecasts', [
                'name' => 'API Test Forecast',
                'base_date' => '2026-01-01',
                'horizon' => '30d',
                'opening_balance' => 10000,
                'scenario' => 'base',
            ]);

        expect($response->status())->toBe(201);
        expect($response->json('forecast.name'))->toBe('API Test Forecast');
        expect($response->json('summary.opening_balance'))->toEqual(10000.0);
    });

    test('GET /api/v1/accounting/treasury/forecasts/{id} returns forecast with summary', function () {
        $forecast = $this->service->generate([
            'name' => 'Detail Test',
            'base_date' => today()->toDateString(),
            'horizon' => '30d',
            'opening_balance' => 5000,
        ], $this->user->id);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/accounting/treasury/forecasts/{$forecast->id}");

        expect($response->status())->toBe(200);
        expect($response->json('forecast.id'))->toBe($forecast->id);
        expect($response->json('summary'))->toBeArray();
    });

    test('GET /api/v1/accounting/treasury/forecasts/{id}/timeline returns daily breakdown', function () {
        $forecast = $this->service->generate([
            'name' => 'Timeline Test',
            'base_date' => '2026-01-01',
            'horizon' => '30d',
            'opening_balance' => 10000,
        ], $this->user->id);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/accounting/treasury/forecasts/{$forecast->id}/timeline");

        expect($response->status())->toBe(200);
        $timeline = $response->json('timeline');
        expect($timeline)->toBeArray();
        expect(count($timeline))->toBeGreaterThan(0);
        expect($timeline[0])->toHaveKeys(['date', 'inflows', 'outflows', 'running_balance']);
    });

    test('GET /api/v1/accounting/treasury/forecasts/{id}/summary returns KPIs', function () {
        $forecast = $this->service->generate([
            'name' => 'Summary Test',
            'base_date' => today()->toDateString(),
            'horizon' => '30d',
            'opening_balance' => 20000,
        ], $this->user->id);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/accounting/treasury/forecasts/{$forecast->id}/summary");

        expect($response->status())->toBe(200);
        expect($response->json('opening_balance'))->toEqual(20000.0);
        expect($response->json('period'))->toBeArray();
    });

    test('POST /api/v1/accounting/treasury/forecasts/{id}/items adds item and recalculates', function () {
        $forecast = $this->service->generate([
            'name' => 'Item Test',
            'base_date' => today()->toDateString(),
            'horizon' => '30d',
            'opening_balance' => 10000,
        ], $this->user->id);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/accounting/treasury/forecasts/{$forecast->id}/items", [
                'date' => today()->addDays(5)->toDateString(),
                'category' => 'client_payment',
                'type' => 'inflow',
                'source' => 'Acme Corp',
                'amount' => 8000,
            ]);

        expect($response->status())->toBe(201);
        expect($response->json('type'))->toBe('inflow');

        $forecast->refresh();
        expect((float) $forecast->projected_closing_balance)->toEqual(18000.0);
    });

    test('POST /api/v1/accounting/treasury/scenarios/compare creates all 3 scenarios', function () {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/accounting/treasury/scenarios/compare', [
                'name' => 'Scenario Compare Test',
                'base_date' => today()->toDateString(),
                'horizon' => '30d',
                'opening_balance' => 50000,
            ]);

        expect($response->status())->toBe(200);
        $scenarios = $response->json('scenarios');
        expect($scenarios)->toHaveKeys(['base', 'optimistic', 'pessimistic']);
    });

    test('POST /api/v1/accounting/treasury/alerts creates alert', function () {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/accounting/treasury/alerts', [
                'name' => 'Low Cash Alert',
                'type' => 'low_balance',
                'threshold_amount' => 10000,
                'days_lookahead' => 14,
                'severity' => 'critical',
            ]);

        expect($response->status())->toBe(201);
        expect($response->json('name'))->toBe('Low Cash Alert');
        expect($response->json('severity'))->toBe('critical');
    });

    test('GET /api/v1/accounting/treasury/alerts lists all alerts', function () {
        TreasuryAlert::factory()->count(2)->create(['created_by' => $this->user->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/accounting/treasury/alerts');

        expect($response->status())->toBe(200);
        expect($response->json('data'))->toHaveCount(2);
    });

    test('GET /api/v1/accounting/treasury/dashboard returns treasury dashboard', function () {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/accounting/treasury/dashboard?horizon=30d');

        expect($response->status())->toBe(200);
        expect($response->json())->toHaveKeys(['forecast', 'summary', 'timeline', 'alerts']);
    });
});
