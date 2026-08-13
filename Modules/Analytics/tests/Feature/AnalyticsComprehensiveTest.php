<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Analytics\Models\ForecastAlert;
use Modules\Analytics\Models\ForecastModel;
use Modules\Analytics\Models\ForecastScenario;
use Modules\Analytics\Services\AiForecastNarrativeService;
use Modules\Analytics\Services\Forecasting\CashflowForecastService;
use Modules\Analytics\Services\Forecasting\DemandForecastService;
use Modules\Analytics\Services\Forecasting\HrForecastService;
use Modules\Analytics\Services\ForecastingEngineService;

uses(RefreshDatabase::class);

// ─── Helpers ──────────────────────────────────────────────────────────────────

function makeTimeSeriesData(int $points = 12): array
{
    $data = [];
    $base = 100000;
    for ($i = 0; $i < $points; $i++) {
        $data[] = [
            'period' => now()->subMonths($points - $i)->format('Y-m'),
            'value'  => $base + rand(-10000, 20000),
        ];
    }
    return $data;
}

// ─── ForecastingEngineService ─────────────────────────────────────────────────

describe('ForecastingEngineService - Linear Regression', function () {
    beforeEach(function () {
        $this->user    = actingAsUser('admin');
        $this->service = app(ForecastingEngineService::class);
    });

    test('performs linear regression on time-series data', function () {
        $data   = makeTimeSeriesData(12);
        $result = $this->service->linearRegression($data, periods: 3);

        expect($result)->toBeArray()
            ->and($result)->toHaveKey('predictions')
            ->and(count($result['predictions']))->toBe(3);
    });

    test('computes moving average forecast', function () {
        $data   = makeTimeSeriesData(12);
        $result = $this->service->movingAverage($data, window: 3, periods: 3);

        expect($result)->toBeArray()
            ->and($result)->toHaveKey('predictions');
    });

    test('applies exponential smoothing (Holt-Winters)', function () {
        $data   = makeTimeSeriesData(24);
        $result = $this->service->exponentialSmoothing($data, alpha: 0.3, periods: 6);

        expect($result)->toBeArray()
            ->and($result)->toHaveKey('predictions')
            ->and(count($result['predictions']))->toBe(6);
    });

    test('selects best algorithm based on historical accuracy', function () {
        $data      = makeTimeSeriesData(18);
        $algorithm = $this->service->selectBestAlgorithm($data);

        expect($algorithm)->toBeString()
            ->and($algorithm)->toBeIn(['linear_regression', 'moving_average', 'exponential_smoothing']);
    });

    test('calculates Mean Absolute Percentage Error (MAPE)', function () {
        $actual    = [100, 110, 120, 130, 140];
        $predicted = [102, 108, 118, 132, 138];

        $mape = $this->service->calculateMape($actual, $predicted);

        expect($mape)->toBeFloat()
            ->and($mape)->toBeCloseTo(1.65, 1.0);
    });
});

// ─── DemandForecastService ────────────────────────────────────────────────────

describe('DemandForecastService', function () {
    beforeEach(function () {
        $this->user    = actingAsUser('admin');
        $this->service = app(DemandForecastService::class);
    });

    test('generates product demand forecast for next 3 months', function () {
        $result = $this->service->forecastProductDemand(
            productId: 1,
            tenantId: 1,
            horizon: 3,
        );

        expect($result)->toBeArray()
            ->and($result)->toHaveKey('forecast');
    });

    test('calculates reorder point from demand and lead time', function () {
        $reorderPoint = $this->service->calculateReorderPoint(
            averageDailyDemand: 50,
            leadTimeDays: 7,
            safetyStock: 100,
        );

        expect($reorderPoint)->toBeInt()
            ->and($reorderPoint)->toBeGreaterThan(0);
    });

    test('applies Africa seasonality adjustments (Ramadan, harvest)', function () {
        $result = $this->service->applySeasonalityFactors(
            forecast: [100, 110, 120],
            country: 'SN',
            months: ['2026-03', '2026-04', '2026-05'],
        );

        expect($result)->toBeArray()
            ->and(count($result))->toBe(3);
    });

    test('detects demand surge pattern in time series', function () {
        $data = makeTimeSeriesData(12);
        // Spike the last value
        $data[11]['value'] = $data[10]['value'] * 3;

        $surge = $this->service->detectDemandSurge($data, threshold: 2.0);

        expect($surge)->toBeArray()
            ->and($surge)->toHaveKey('detected');
    });

    test('generates category-level demand forecast', function () {
        $result = $this->service->forecastCategoryDemand(
            category: 'textile',
            tenantId: 1,
            horizon: 6,
        );

        expect($result)->toBeArray();
    });
});

// ─── CashflowForecastService ──────────────────────────────────────────────────

describe('CashflowForecastService', function () {
    beforeEach(function () {
        $this->user    = actingAsUser('admin');
        $this->service = app(CashflowForecastService::class);
    });

    test('generates 90-day cashflow projection', function () {
        $result = $this->service->project90Days(tenantId: 1);

        expect($result)->toBeArray()
            ->and($result)->toHaveKey('days')
            ->and(count($result['days']))->toBe(90);
    });

    test('detects cashflow deficit gap', function () {
        $result = $this->service->detectGaps(
            projection: array_fill(0, 90, -5000),
            threshold: 0,
        );

        expect($result)->toBeArray()
            ->and($result)->toHaveKey('gaps');
    });

    test('calculates running balance from inflows and outflows', function () {
        $balance = $this->service->calculateRunningBalance(
            openingBalance: 500000,
            inflows: [100000, 200000, 150000],
            outflows: [80000, 120000, 90000],
        );

        expect($balance)->toBeArray()
            ->and(count($balance))->toBe(3);
    });

    test('generates OHADA-compliant cashflow projection format', function () {
        $result = $this->service->generateOhadaReport(tenantId: 1, year: 2026);

        expect($result)->toBeArray()
            ->and($result)->toHaveKey('currency');
    });

    test('flags cashflow_deficit alert when balance drops below threshold', function () {
        $gaps = [
            ['day' => 15, 'balance' => -200000, 'currency' => 'XOF'],
        ];

        $alerts = $this->service->generateAlerts($gaps);

        expect($alerts)->toBeArray()
            ->and(count($alerts))->toBeGreaterThan(0)
            ->and($alerts[0]['type'])->toBe('cashflow_deficit');
    });
});

// ─── HrForecastService ────────────────────────────────────────────────────────

describe('HrForecastService', function () {
    beforeEach(function () {
        $this->user    = actingAsUser('admin');
        $this->service = app(HrForecastService::class);
    });

    test('forecasts headcount gap for next 6 months', function () {
        $result = $this->service->forecastHeadcountGap(
            tenantId: 1,
            horizon: 6,
        );

        expect($result)->toBeArray()
            ->and($result)->toHaveKey('forecast');
    });

    test('scores turnover risk for employees', function () {
        $result = $this->service->scoreTurnoverRisk(tenantId: 1);

        expect($result)->toBeArray();
    });

    test('projects payroll cost for next quarter', function () {
        $result = $this->service->projectPayrollCost(
            tenantId: 1,
            months: 3,
        );

        expect($result)->toBeArray()
            ->and($result)->toHaveKey('total_cost');
    });

    test('identifies high-risk departure employees', function () {
        $result = $this->service->getHighRiskEmployees(
            tenantId: 1,
            threshold: 0.7,
        );

        expect($result)->toBeArray();
    });
});

// ─── AiForecastNarrativeService ───────────────────────────────────────────────

describe('AiForecastNarrativeService (mocked Claude API)', function () {
    beforeEach(function () {
        $this->user    = actingAsUser('admin');
        $this->service = app(AiForecastNarrativeService::class);
    });

    test('generates AI narrative for demand forecast', function () {
        Http::fake([
            'https://api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => json_encode([
                    'summary'    => 'La demande devrait augmenter de 12% au Q3.',
                    'key_factors'=> ['Ramadan', 'Saison des pluies'],
                    'risks'      => ['Rupture approvisionnement'],
                    'actions'    => ['Augmenter stock de sécurité de 20%'],
                ])]],
            ], 200),
        ]);

        $forecastData = [
            'module'      => 'demand',
            'predictions' => makeTimeSeriesData(3),
            'mape'        => 2.5,
        ];

        $narrative = $this->service->generate($forecastData, locale: 'fr');

        expect($narrative)->toBeArray()
            ->and($narrative)->toHaveKey('summary');
    });

    test('falls back gracefully when Claude API is unavailable', function () {
        Http::fake([
            'https://api.anthropic.com/*' => Http::response([], 503),
        ]);

        $forecastData = ['module' => 'cashflow', 'predictions' => []];

        $narrative = $this->service->generate($forecastData, locale: 'fr');

        expect($narrative)->toBeArray()
            ->and($narrative)->toHaveKey('summary');
    });

    test('generates narrative in English when locale is en', function () {
        Http::fake([
            'https://api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => json_encode([
                    'summary'     => 'Revenue is expected to grow 8% next quarter.',
                    'key_factors' => ['Seasonal demand'],
                    'risks'       => [],
                    'actions'     => ['Increase safety stock'],
                ])]],
            ], 200),
        ]);

        $forecastData = ['module' => 'demand', 'predictions' => makeTimeSeriesData(3)];

        $narrative = $this->service->generate($forecastData, locale: 'en');

        expect($narrative)->toBeArray()
            ->and($narrative)->toHaveKey('summary');
    });
});

// ─── Alert Engine ─────────────────────────────────────────────────────────────

describe('Alert Engine', function () {
    beforeEach(function () {
        $this->user    = actingAsUser('admin');
        $this->service = app(ForecastingEngineService::class);
    });

    test('generates stockout_risk alert when stock falls below reorder point', function () {
        $alert = $this->service->generateAlert([
            'type'      => 'stockout_risk',
            'product'   => 'Tissu coton',
            'days_left' => 3,
            'severity'  => 'critical',
            'tenant_id' => 1,
        ]);

        expect($alert)->toBeArray()
            ->and($alert['type'])->toBe('stockout_risk');
    });

    test('generates demand_surge alert when demand spikes 200%', function () {
        $alert = $this->service->generateAlert([
            'type'       => 'demand_surge',
            'product'    => 'Uniforme scolaire',
            'surge_pct'  => 210,
            'severity'   => 'warning',
            'tenant_id'  => 1,
        ]);

        expect($alert)->toBeArray()
            ->and($alert['type'])->toBe('demand_surge');
    });

    test('generates production_bottleneck alert for capacity overload', function () {
        $alert = $this->service->generateAlert([
            'type'          => 'production_bottleneck',
            'work_center'   => 'Atelier couture',
            'utilization'   => 115,
            'severity'      => 'critical',
            'tenant_id'     => 1,
        ]);

        expect($alert)->toBeArray()
            ->and($alert['type'])->toBe('production_bottleneck');
    });

    test('generates hr_shortage alert for headcount gap', function () {
        $alert = $this->service->generateAlert([
            'type'       => 'hr_shortage',
            'department' => 'Production',
            'gap'        => 5,
            'severity'   => 'warning',
            'tenant_id'  => 1,
        ]);

        expect($alert)->toBeArray()
            ->and($alert['type'])->toBe('hr_shortage');
    });

    test('acknowledges an alert and updates status', function () {
        $alert = ForecastAlert::factory()->create([
            'type'      => 'stockout_risk',
            'status'    => 'active',
            'tenant_id' => 1,
        ]);

        $this->service->acknowledgeAlert($alert->id);

        $alert->refresh();
        expect($alert->status)->toBe('acknowledged');
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
    });

    test('GET /api/v1/forecasting/demand?product_id=1 returns demand forecast', function () {
        $response = $this->getJson('/api/v1/forecasting/demand?product_id=1');
        $response->assertStatus(200);
    });

    test('GET /api/v1/forecasting/hr returns HR forecast data', function () {
        $response = $this->getJson('/api/v1/forecasting/hr');
        $response->assertStatus(200);
    });

    test('GET /api/v1/forecasting/production returns production forecast', function () {
        $response = $this->getJson('/api/v1/forecasting/production');
        $response->assertStatus(200);
    });

    test('GET /api/v1/forecasting/scenarios lists forecast scenarios', function () {
        $response = $this->getJson('/api/v1/forecasting/scenarios');
        $response->assertStatus(200);
    });

    test('POST /api/v1/forecasting/scenarios creates a what-if scenario', function () {
        $response = $this->postJson('/api/v1/forecasting/scenarios', [
            'name'        => 'Scénario Ramadan +20%',
            'assumptions' => ['demand_multiplier' => 1.2],
            'module'      => 'demand',
        ]);
        $response->assertStatus(201);
    });

    test('POST /api/v1/forecasting/ai/narrative generates AI narrative', function () {
        Http::fake([
            'https://api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => json_encode([
                    'summary'     => 'La demande augmente.',
                    'key_factors' => [],
                    'risks'       => [],
                    'actions'     => [],
                ])]],
            ], 200),
        ]);

        $response = $this->postJson('/api/v1/forecasting/ai/narrative', [
            'module'      => 'demand',
            'forecast_id' => 1,
            'locale'      => 'fr',
        ]);
        $response->assertStatus(200);
    });
});
