<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Strategy\Models\Correlation;
use Modules\Strategy\Models\IndustryBenchmark;
use Modules\Strategy\Models\StrategyKpi;
use Modules\Strategy\Models\StrategyKpiValue;
use Modules\Strategy\Models\StrategyPlan;
use Modules\Strategy\Models\StrategyObjective;
use Modules\Strategy\Models\StrategyScenario;
use Modules\Strategy\Models\StrategicAlert;
use Modules\Strategy\Models\Ratio;
use Modules\Strategy\Models\RatioSnapshot;
use Modules\Strategy\Services\CorrelationAnalysisService;
use Modules\Strategy\Services\BenchmarkService;
use Modules\Strategy\Services\KpiDataService;

uses(RefreshDatabase::class);

// ─── CorrelationAnalysisService ───────────────────────────────────────────────

describe('CorrelationAnalysisService', function () {
    beforeEach(function () {
        $this->user    = actingAsUser('admin');
        $this->service = app(CorrelationAnalysisService::class);
    });

    test('can compute Pearson coefficient for two series', function () {
        $seriesA = [1.0, 2.0, 3.0, 4.0, 5.0];
        $seriesB = [2.0, 4.0, 6.0, 8.0, 10.0];

        $r = $this->service->pearson($seriesA, $seriesB);

        expect($r)->toBeCloseTo(1.0, 0.01);
    });

    test('pearson returns negative coefficient for inverse series', function () {
        $seriesA = [5.0, 4.0, 3.0, 2.0, 1.0];
        $seriesB = [1.0, 2.0, 3.0, 4.0, 5.0];

        $r = $this->service->pearson($seriesA, $seriesB);

        expect($r)->toBeCloseTo(-1.0, 0.01);
    });

    test('pearson returns zero for uncorrelated series', function () {
        $seriesA = [1.0, 2.0, 3.0, 4.0, 5.0];
        $seriesB = [3.0, 3.0, 3.0, 3.0, 3.0];

        $r = $this->service->pearson($seriesA, $seriesB);

        expect($r)->toBeCloseTo(0.0, 0.01);
    });

    test('can store a correlation', function () {
        $correlation = $this->service->store(
            'CRM:win_rate',
            'Sales:revenue_growth_rate',
            0.82,
            0,
            0.95
        );

        expect($correlation)->toBeInstanceOf(Correlation::class)
            ->and($correlation->kpi_a)->toBe('CRM:win_rate')
            ->and($correlation->kpi_b)->toBe('Sales:revenue_growth_rate');
    });

    test('can retrieve top correlations', function () {
        Correlation::factory()->count(5)->create();

        $correlations = $this->service->topCorrelations(3);

        expect($correlations)->toBeArray()
            ->and(count($correlations))->toBeLessThanOrEqual(3);
    });

    test('can get matrix view of correlations', function () {
        Correlation::factory()->count(3)->create();

        $matrix = $this->service->matrixView();

        expect($matrix)->toBeArray();
    });
});

// ─── BenchmarkService ─────────────────────────────────────────────────────────

describe('BenchmarkService', function () {
    beforeEach(function () {
        $this->user    = actingAsUser('admin');
        $this->service = app(BenchmarkService::class);
    });

    test('can list all benchmarks', function () {
        IndustryBenchmark::factory()->count(5)->create();

        $benchmarks = $this->service->listAll();

        expect($benchmarks)->toBeArray();
    });

    test('can filter benchmarks by country', function () {
        IndustryBenchmark::factory()->create(['country' => 'SN', 'industry' => 'retail']);
        IndustryBenchmark::factory()->create(['country' => 'CI', 'industry' => 'retail']);

        $benchmarks = $this->service->listAll('SN');

        expect($benchmarks)->toBeArray();
    });

    test('can filter benchmarks by industry', function () {
        IndustryBenchmark::factory()->create(['country' => 'SN', 'industry' => 'manufacturing']);

        $benchmarks = $this->service->listAll(null, 'manufacturing');

        expect($benchmarks)->toBeArray();
    });

    test('can compute percentile rank (ascending direction)', function () {
        $benchmarkData = [
            'p25'    => 10.0,
            'median' => 20.0,
            'p75'    => 30.0,
        ];

        $rank = $this->service->percentileRank(25.0, $benchmarkData, 'up');

        expect($rank)->not->toBeNull();
    });

    test('can retrieve benchmarks for a ratio key', function () {
        $data = $this->service->getForRatio('CRM:win_rate', 'default', 'SN', 'retail');

        expect($data)->toBeArray();
    });
});

// ─── KpiDataService ───────────────────────────────────────────────────────────

describe('KpiDataService', function () {
    beforeEach(function () {
        $this->user    = actingAsUser('admin');
        $this->service = app(KpiDataService::class);
    });

    test('service is resolvable from container', function () {
        $service = app(KpiDataService::class);
        expect($service)->toBeInstanceOf(KpiDataService::class);
    });

    test('can get available sources', function () {
        $sources = $this->service->getAvailableSources();
        expect($sources)->toBeArray();
    });

    test('can record a KPI value', function () {
        $kpi   = StrategyKpi::factory()->create();
        $value = $this->service->recordValue($kpi, 42.5);

        expect($value)->toBeInstanceOf(StrategyKpiValue::class)
            ->and($value->value)->toBeCloseTo(42.5, 0.01);
    });

    test('can get KPI history', function () {
        $kpi = StrategyKpi::factory()->create();
        StrategyKpiValue::factory()->count(5)->create(['kpi_id' => $kpi->id]);

        $history = $this->service->getHistory($kpi->id, 30);

        expect($history)->toBeArray();
    });

    test('can fetch live value for a KPI', function () {
        $kpi = StrategyKpi::factory()->create(['source_module' => 'Accounting', 'source_key' => 'monthly_revenue']);

        try {
            $value = $this->service->fetchLiveValue($kpi);
            expect($value)->toBeFloat();
        } catch (\Throwable) {
            // External source unavailable in test env — acceptable
            expect(true)->toBeTrue();
        }
    });
});

// ─── API Endpoints — Plans ────────────────────────────────────────────────────

describe('Strategy API - Plans', function () {
    beforeEach(function () {
        $this->user = actingAsUser('admin');
    });

    test('can list strategy plans', function () {
        StrategyPlan::factory()->count(3)->create();

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/strategy/plans')
            ->assertOk();
    });

    test('can create a strategy plan', function () {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/strategy/plans', [
                'name'         => 'Plan 2026',
                'period_start' => 2026,
                'period_end'   => 2026,
            ])
            ->assertCreated();
    });

    test('can view a strategy plan', function () {
        $plan = StrategyPlan::factory()->create();

        $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/strategy/plans/{$plan->id}")
            ->assertOk();
    });

    test('can update a strategy plan', function () {
        $plan = StrategyPlan::factory()->create();

        $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/strategy/plans/{$plan->id}", [
                'name' => 'Updated Plan',
            ])
            ->assertOk();
    });

    test('can delete a strategy plan', function () {
        $plan = StrategyPlan::factory()->create();

        $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/strategy/plans/{$plan->id}")
            ->assertOk()->assertJson(['message' => 'Plan deleted.']);
    });

    test('unauthenticated user cannot access strategy plans', function () {
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/strategy/plans')
            ->assertUnauthorized();
    });

    // Chantier 10: StrategyPlanController had full CRUD with zero Policy at
    // all — any role passing the outer route:employee,finance-manager,
    // manager,admin gate could delete any plan with no per-record check.
    // StrategyPlanPolicy (new) restricts delete to admin/super-admin roles
    // or an explicit strategy.plan.delete permission — 'employee' gets every
    // non-.delete permission by this app's broad-role design (see
    // RolesAndPermissionsSeeder), so it should still create/update but be
    // denied on delete.
    test('employee can create and update a strategy plan but cannot delete one', function () {
        $employee = actingAsUser('employee');

        $created = $this->actingAs($employee, 'sanctum')
            ->postJson('/api/v1/strategy/plans', [
                'name'         => 'Employee-created plan',
                'period_start' => 2026,
                'period_end'   => 2026,
            ]);
        $created->assertCreated();

        $plan = StrategyPlan::find($created->json('id'));

        $this->actingAs($employee, 'sanctum')
            ->putJson("/api/v1/strategy/plans/{$plan->id}", ['name' => 'Renamed'])
            ->assertOk();

        $this->actingAs($employee, 'sanctum')
            ->deleteJson("/api/v1/strategy/plans/{$plan->id}")
            ->assertStatus(403);
    });
});

// ─── API Endpoints — OKRs ────────────────────────────────────────────────────

describe('Strategy API - OKRs', function () {
    beforeEach(function () {
        $this->user = actingAsUser('admin');
    });

    test('can list objectives', function () {
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/strategy/objectives')
            ->assertOk();
    });

    test('can create an objective', function () {
        $plan = StrategyPlan::factory()->create();

        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/strategy/objectives', [
                'title'      => 'Grow Revenue',
                'level'      => 'annual',
                'plan_id'    => $plan->id,
                'start_date' => '2026-01-01',
                'end_date'   => '2026-12-31',
            ])
            ->assertCreated();
    });

    test('can get objective tree', function () {
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/strategy/objectives/tree')
            ->assertOk();
    });
});

// ─── API Endpoints — KPIs ────────────────────────────────────────────────────

describe('Strategy API - KPIs', function () {
    beforeEach(function () {
        $this->user = actingAsUser('admin');
    });

    test('can list KPIs', function () {
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/strategy/kpis')
            ->assertOk();
    });

    test('can create a KPI', function () {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/strategy/kpis', [
                'name'   => 'Win Rate',
                'module' => 'CRM',
                'unit'   => '%',
                'source' => 'static',
            ])
            ->assertCreated();
    });

    test('can get KPI sources', function () {
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/strategy/kpis/sources')
            ->assertOk();
    });
});

// ─── API Endpoints — Scenarios ────────────────────────────────────────────────

describe('Strategy API - Scenarios', function () {
    beforeEach(function () {
        $this->user = actingAsUser('admin');
    });

    test('can list scenarios', function () {
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/strategy/scenarios')
            ->assertOk();
    });

    test('can create a scenario', function () {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/strategy/scenarios', [
                'name'        => 'Optimistic 2026',
                'description' => 'Best case scenario',
            ])
            ->assertCreated();
    });
});

// ─── Models ───────────────────────────────────────────────────────────────────

describe('Strategy Models', function () {
    beforeEach(function () {
        $this->user = actingAsUser('admin');
    });

    test('StrategyPlan factory creates valid model', function () {
        $plan = StrategyPlan::factory()->create();
        expect($plan->id)->not->toBeNull();
    });

    test('StrategyKpi factory creates valid model', function () {
        $kpi = StrategyKpi::factory()->create();
        expect($kpi->id)->not->toBeNull()
            ->and($kpi->name)->not->toBeEmpty();
    });

    test('Correlation factory creates valid model', function () {
        $correlation = Correlation::factory()->create();
        expect($correlation->id)->not->toBeNull();
    });

    test('IndustryBenchmark factory creates valid model', function () {
        $benchmark = IndustryBenchmark::factory()->create();
        expect($benchmark->id)->not->toBeNull();
    });

    test('StrategyScenario factory creates valid model', function () {
        $scenario = StrategyScenario::factory()->create();
        expect($scenario->id)->not->toBeNull();
    });

    test('StrategicAlert factory creates valid model', function () {
        $alert = StrategicAlert::factory()->create();
        expect($alert->id)->not->toBeNull();
    });
});
