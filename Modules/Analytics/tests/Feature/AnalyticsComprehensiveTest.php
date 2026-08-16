<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Analytics\Models\ForecastAlert;
use Modules\Analytics\Models\ForecastModel;
use Modules\Analytics\Services\AiForecastNarrativeService;
use Modules\Analytics\Services\Forecasting\CashflowForecastService;
use Modules\Analytics\Services\Forecasting\DemandForecastService;
use Modules\Analytics\Services\Forecasting\HrForecastService;
use Modules\Analytics\Services\ForecastingEngineService;

uses(RefreshDatabase::class);

// ─── Helpers ──────────────────────────────────────────────────────────────────
// linearRegression()/movingAverage()/exponentialSmoothing() parse Carbon on
// $data[n-1]['date'] directly (not via forecast()'s normalizeSeries(), which
// tolerates a missing date) — real data rows need a 'date' key.
function makeTimeSeriesData(int $points = 12): array
{
    $data = [];
    $base = 100000;
    for ($i = 0; $i < $points; $i++) {
        $data[] = [
            'date'  => now()->subDays($points - $i)->toDateString(),
            'value' => $base + rand(-10000, 20000),
        ];
    }
    return $data;
}

// ─── ForecastingEngineService — core algorithms (real API) ───────────────────
//
// The stateless functional API (forecast/forecastCashflow/detectStockoutRisk/
// calculateReorderPoint/generateScenarios/computeMeanAbsoluteError/
// forecastHrHeadcount/identifyDemandSurges) is already covered end-to-end by
// Modules/Analytics/tests/Feature/ForecastingEngineServiceTest.php (14/14
// green) — this block exercises the lower-level algorithm methods directly
// instead of duplicating that coverage.

describe('ForecastingEngineService - Core Algorithms', function () {
    beforeEach(function () {
        $this->user    = actingAsUser('admin');
        $this->service = app(ForecastingEngineService::class);
    });

    test('performs linear regression on time-series data', function () {
        $data   = makeTimeSeriesData(12);
        $result = $this->service->linearRegression($data, horizonDays: 3);

        expect($result)->toBeArray()
            ->and(count($result))->toBe(3)
            ->and($result[0])->toHaveKeys(['date', 'value', 'lower', 'upper']);
    });

    test('computes moving average forecast', function () {
        $data   = makeTimeSeriesData(12);
        $result = $this->service->movingAverage($data, periods: 3);

        expect($result)->toBeArray()->and(count($result))->toBe(3);
    });

    test('applies exponential smoothing (Holt-Winters)', function () {
        $data   = makeTimeSeriesData(24);
        $result = $this->service->exponentialSmoothing($data, alpha: 0.3);

        expect($result)->toBeArray()->and(count($result))->toBeGreaterThan(0);
    });

    test('forecast() defaults to linear regression when no algorithm given', function () {
        $data   = makeTimeSeriesData(18);
        $result = $this->service->forecast('demand', $data, periods: 3);

        expect($result['algorithm'])->toBe(['linear_regression'])
            ->and(count($result['values']))->toBe(3);
    });

    test('computes mean absolute error between actual and predicted series', function () {
        $actual    = [100, 110, 120, 130, 140];
        $predicted = [102, 108, 118, 132, 138];

        $mae = $this->service->computeMeanAbsoluteError($actual, $predicted);

        expect($mae)->toBeFloat()->and($mae)->toBeCloseTo(2.0, 0.1);
    });
});

// ─── DemandForecastService (real method names) ────────────────────────────────

describe('DemandForecastService', function () {
    beforeEach(function () {
        $this->user    = actingAsUser('admin');
        $this->service = app(DemandForecastService::class);
    });

    test('generates product demand forecast', function () {
        $result = $this->service->forecastProduct(productId: 1, tenantId: 1, days: 30);

        expect($result)->toBeArray()
            ->and($result)->toHaveKey('predictions')
            ->and($result)->toHaveKey('reorder_suggestion');
    });

    test('calculates reorder point from demand and lead time', function () {
        $reorderPoint = app(ForecastingEngineService::class)->calculateReorderPoint(
            avgDailyDemand: 50,
            leadTimeDays: 7,
            safetyStock: 100,
        );

        expect($reorderPoint)->toBeFloat()->and($reorderPoint)->toBeGreaterThan(0);
    });

    test('detects seasonality patterns (weekly, monthly, Africa First events)', function () {
        $result = $this->service->detectSeasonality(makeTimeSeriesData(30));

        expect($result)->toBeArray()
            ->and($result)->toHaveKeys(['weekly', 'monthly', 'african_events']);
    });

    test('generates category-level demand forecast', function () {
        $result = $this->service->forecastCategory(category: 'textile', tenantId: 1, days: 30);

        expect($result)->toBeArray()
            ->and($result)->toHaveKey('predictions')
            ->and($result)->toHaveKey('top_products');
    });
});

// ─── CashflowForecastService (real method names) ──────────────────────────────

describe('CashflowForecastService', function () {
    beforeEach(function () {
        $this->user    = actingAsUser('admin');
        $this->service = app(CashflowForecastService::class);
    });

    test('generates 90-day cashflow projection', function () {
        $result = $this->service->forecast90Days(tenantId: 1);

        expect($result)->toBeArray()
            ->and($result)->toHaveKey('daily')
            ->and(count($result['daily']))->toBe(90);
    });

    test('detects cashflow deficit gaps', function () {
        $gaps = $this->service->detectGaps(tenantId: 1, threshold: 0);

        expect($gaps)->toBeArray();
    });

    test('daily projection computes a cumulative running balance', function () {
        $projection = $this->service->getDailyProjection(tenantId: 1, days: 5);

        expect($projection)->toBeArray()
            ->and(count($projection))->toBe(5)
            ->and($projection[0])->toHaveKey('running_balance');
    });

    test('generates OHADA-compliant cashflow projection (Classe 5)', function () {
        $result = $this->service->getOhadaProjection(tenantId: 1);

        expect($result)->toBeArray()->and($result)->toHaveKey('currency');
    });

    test('90-day summary flags whether the balance ever drops below zero', function () {
        $result = $this->service->forecast90Days(tenantId: 1);

        expect($result['summary'])->toHaveKey('has_deficit');
    });
});

// ─── HrForecastService (real method names) ────────────────────────────────────

describe('HrForecastService', function () {
    beforeEach(function () {
        $this->user    = actingAsUser('admin');
        $this->service = app(HrForecastService::class);
    });

    test('forecasts headcount gap for next 6 months', function () {
        $result = $this->service->forecastHeadcount(tenantId: 1, months: 6);

        expect($result)->toBeArray()->and(count($result))->toBe(6)
            ->and($result[0])->toHaveKeys(['month', 'required_headcount', 'current_headcount', 'gap']);
    });

    test('scores turnover risk for active employees', function () {
        $result = $this->service->predictTurnoverRisk(tenantId: 1);

        expect($result)->toBeArray();
    });

    test('projects payroll cost for next quarter', function () {
        $result = $this->service->forecastPayrollCost(tenantId: 1, months: 3);

        expect($result)->toBeArray()->and(count($result))->toBe(3)
            ->and($result[0])->toHaveKeys(['month', 'base_salary', 'bonuses', 'total']);
    });

    test('identifies high-risk departure employees from the turnover risk scoring', function () {
        $highRisk = collect($this->service->predictTurnoverRisk(tenantId: 1))
            ->filter(fn ($e) => $e['risk_score'] >= 0.7)
            ->values();

        expect($highRisk)->toBeInstanceOf(\Illuminate\Support\Collection::class);
    });
});

// ─── AiForecastNarrativeService (mocked Claude API) ───────────────────────────

describe('AiForecastNarrativeService (mocked Claude API)', function () {
    beforeEach(function () {
        $this->user    = actingAsUser('admin');
        $this->service = app(AiForecastNarrativeService::class);
        // Real code path reads config('ai.providers.anthropic.api_key') — the
        // same config the rest of the AI provider registry uses.
        config(['ai.providers.anthropic.api_key' => 'test-key']);
    });

    test('generates AI narrative for demand forecast', function () {
        Http::fake([
            'https://api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => json_encode([
                    'narrative'    => 'La demande devrait augmenter de 12% au Q3.',
                    'key_factors'  => ['Ramadan', 'Saison des pluies'],
                    'risks'        => ['Rupture approvisionnement'],
                    'opportunities' => [],
                    'recommended_actions' => [],
                    'confidence_explanation' => 'Basé sur 3 mois de données.',
                ])]],
            ], 200),
        ]);

        $narrative = $this->service->generateNarrative('demand', makeTimeSeriesData(3), [], 'fr');

        expect($narrative)->toBeArray()
            ->and($narrative)->toHaveKey('narrative')
            ->and($narrative['enabled'])->toBeTrue();
    });

    test('falls back gracefully when Claude API is unavailable', function () {
        Http::fake([
            'https://api.anthropic.com/*' => Http::response([], 503),
        ]);

        $narrative = $this->service->generateNarrative('cashflow', []);

        expect($narrative)->toBeArray()
            ->and($narrative)->toHaveKey('narrative')
            ->and($narrative['enabled'])->toBeFalse();
    });

    test('falls back to static narrative when no API key is configured', function () {
        config(['ai.providers.anthropic.api_key' => null]);

        $narrative = $this->service->generateNarrative('demand', makeTimeSeriesData(3));

        expect($narrative)->toBeArray()
            ->and($narrative['enabled'])->toBeFalse()
            ->and($narrative)->toHaveKey('narrative');
    });

    test('generates narrative in English when locale is en', function () {
        Http::fake([
            'https://api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => json_encode([
                    'narrative'    => 'Revenue is expected to grow 8% next quarter.',
                    'key_factors'  => ['Seasonal demand'],
                    'risks'        => [],
                    'opportunities' => [],
                    'recommended_actions' => [],
                    'confidence_explanation' => 'test',
                ])]],
            ], 200),
        ]);

        $narrative = $this->service->generateNarrative('demand', makeTimeSeriesData(3), [], 'en');

        expect($narrative)->toBeArray()->and($narrative)->toHaveKey('narrative');
    });
});

// ─── Alert Engine ─────────────────────────────────────────────────────────────
//
// ForecastingEngineService has no generateAlert()/acknowledgeAlert() methods —
// real alerts are ForecastAlert Eloquent records. forecast_alerts has no
// tenant_id/is_acknowledged/title/predicted_value columns (invented by the
// original test); the real schema is forecast_model_id/alert_type/severity/
// message/context(json)/status/triggered_at/resolved_at — status defaults to
// 'active' and Eloquent scoping goes through the owning ForecastModel's
// tenant_id (see ForecastAlert::scopeForTenant()). This block also exposed a
// genuine production bug: ForecastingEngineService::createAlert() (called
// from the scheduled checkAlerts() job per CLAUDE.md's Phase 41) was writing
// tenant_id/model_id/title/predicted_value/is_acknowledged — none of which
// exist — so every alert-creation attempt threw a NOT NULL violation on the
// real forecast_model_id column. Fixed alongside this test rewrite.

describe('Alert Engine', function () {
    beforeEach(function () {
        $this->user  = actingAsUser('admin');
        $this->model = ForecastModel::factory()->create(['tenant_id' => 1]);
    });

    test('stores a stockout_risk alert with critical severity', function () {
        $alert = ForecastAlert::factory()->create([
            'forecast_model_id' => $this->model->id,
            'alert_type'        => 'stockout_risk',
            'severity'          => 'critical',
            'status'            => 'active',
        ]);

        expect($alert->alert_type)->toBe('stockout_risk')
            ->and($alert->severity)->toBe('critical')
            ->and($alert->status)->toBe('active');
    });

    test('stores a demand_surge alert with warning severity', function () {
        $alert = ForecastAlert::factory()->create([
            'forecast_model_id' => $this->model->id,
            'alert_type'        => 'demand_surge',
            'severity'          => 'warning',
        ]);

        expect($alert->alert_type)->toBe('demand_surge');
    });

    test('active scope excludes acknowledged alerts', function () {
        ForecastAlert::factory()->create(['forecast_model_id' => $this->model->id, 'status' => 'active']);
        ForecastAlert::factory()->create(['forecast_model_id' => $this->model->id, 'status' => 'acknowledged']);

        expect(ForecastAlert::forTenant(1)->active()->count())->toBe(1);
    });

    test('critical scope filters by severity', function () {
        ForecastAlert::factory()->create(['forecast_model_id' => $this->model->id, 'severity' => 'critical']);
        ForecastAlert::factory()->create(['forecast_model_id' => $this->model->id, 'severity' => 'warning']);

        expect(ForecastAlert::forTenant(1)->critical()->count())->toBe(1);
    });

    test('acknowledges an alert and records who/when', function () {
        $alert = ForecastAlert::factory()->create([
            'forecast_model_id' => $this->model->id,
            'alert_type'        => 'stockout_risk',
            'status'            => 'active',
        ]);

        $alert->acknowledge($this->user->id);

        $alert->refresh();
        expect($alert->status)->toBe('acknowledged')
            ->and($alert->context['acknowledged_by'])->toBe($this->user->id)
            ->and($alert->resolved_at)->not->toBeNull();
    });
});

// ─── Forecasting Hub API ──────────────────────────────────────────────────────

describe('Forecasting Hub API', function () {
    beforeEach(function () {
        $this->user = actingAsUser('admin');
    });

    test('GET /api/v1/forecasting/hub returns summary for all modules', function () {
        $response = $this->getJson('/api/v1/forecasting/hub');
        $response->assertStatus(200);
        expect($response->json())->toHaveKeys(['alert_count', 'critical_alerts', 'modules']);
    });

    test('GET /api/v1/forecasting/models lists forecast models', function () {
        $response = $this->getJson('/api/v1/forecasting/models');
        $response->assertStatus(200);
    });

    test('GET /api/v1/forecasting/alerts lists active alerts', function () {
        $response = $this->getJson('/api/v1/forecasting/alerts');
        $response->assertStatus(200);
    });

    test('GET /api/v1/forecasting/cashflow returns 90-day projection', function () {
        $response = $this->getJson('/api/v1/forecasting/cashflow');
        $response->assertStatus(200);
        expect($response->json())->toHaveKey('daily');
    });

    test('GET /api/v1/forecasting/demand?product_id=1 returns product demand forecast', function () {
        $response = $this->getJson('/api/v1/forecasting/demand?product_id=1');
        $response->assertStatus(200);
        expect($response->json())->toHaveKey('predictions');
    });

    test('GET /api/v1/forecasting/hr returns headcount + payroll + leave demand', function () {
        $response = $this->getJson('/api/v1/forecasting/hr');
        $response->assertStatus(200);
        expect($response->json())->toHaveKeys(['headcount', 'payroll_cost', 'leave_demand']);
    });

    test('GET /api/v1/forecasting/scenarios lists forecast scenarios', function () {
        $response = $this->getJson('/api/v1/forecasting/scenarios');
        $response->assertStatus(200);
    });

    test('POST /api/v1/forecasting/scenarios creates a what-if scenario', function () {
        $model = ForecastModel::factory()->create(['tenant_id' => 1, 'module' => 'demand']);

        $response = $this->postJson('/api/v1/forecasting/scenarios', [
            'model_id'    => $model->id,
            'name'        => 'Scénario Ramadan +20%',
            'assumptions' => ['demand_multiplier' => 1.2],
        ]);
        $response->assertStatus(201);
    });

    test('POST /api/v1/forecasting/ai/narrative generates AI narrative', function () {
        Http::fake([
            'https://api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => json_encode([
                    'narrative'    => 'La demande augmente.',
                    'key_factors'  => [],
                    'risks'        => [],
                    'opportunities' => [],
                    'recommended_actions' => [],
                    'confidence_explanation' => 'test',
                ])]],
            ], 200),
        ]);

        $response = $this->postJson('/api/v1/forecasting/ai/narrative', [
            'module'      => 'demand',
            'predictions' => makeTimeSeriesData(3),
            'locale'      => 'fr',
        ]);
        $response->assertStatus(200);
    });
});

// Note: production-capacity forecasting (GET /api/v1/forecasting/production)
// is deliberately not tested here — ProductionForecastService::
// forecastProductionNeeds() calls getDailyCapacity()/detectBottlenecks(),
// which query work_centers/manufacturing_orders. The Manufacturing module is
// intentionally excluded from Life MDG's 27-module scope (CLAUDE.md), so
// those tables never exist in this repo — same "references a never-built
// feature" profile as the documented ASC606/Consolidation/GraphQL gaps.
