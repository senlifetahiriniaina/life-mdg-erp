<?php

declare(strict_types=1);

use App\Models\User;
use Modules\BI\Models\BiAnomaly;
use Modules\BI\Models\Forecast;
use Modules\BI\Models\PredictiveModel;
use Modules\BI\Services\PredictiveAnalyticsService;


// ── Service helper ────────────────────────────────────────────────────────────

function predictiveService(): PredictiveAnalyticsService
{
    return new PredictiveAnalyticsService;
}

function linearDataPoints(int $n = 10, float $slope = 100.0, float $base = 1000.0): array
{
    $points = [];
    for ($i = 0; $i < $n; $i++) {
        $points[] = [
            'date' => now()->subDays($n - $i)->toDateString(),
            'value' => $base + $slope * $i,
        ];
    }

    return $points;
}

// ── PredictiveModel model unit tests ─────────────────────────────────────────

test('PredictiveModel isActive returns true when active', function () {
    $model = PredictiveModel::factory()->create(['is_active' => true]);
    expect($model->isActive())->toBeTrue();
});

test('PredictiveModel isActive returns false when inactive', function () {
    $model = PredictiveModel::factory()->create(['is_active' => false]);
    expect($model->isActive())->toBeFalse();
});

test('PredictiveModel latestForecast returns most recent', function () {
    $model = PredictiveModel::factory()->create();
    Forecast::factory()->create([
        'predictive_model_id' => $model->id,
        'forecast_date' => now()->addDay(),
    ]);
    $latest = Forecast::factory()->create([
        'predictive_model_id' => $model->id,
        'forecast_date' => now()->addDays(5),
    ]);

    expect($model->latestForecast()->id)->toBe($latest->id);
});

test('PredictiveModel forecastAccuracy averages error_percent', function () {
    $model = PredictiveModel::factory()->create();
    Forecast::factory()->create(['predictive_model_id' => $model->id, 'error_percent' => 4.0, 'forecast_date' => now()->addDay()]);
    Forecast::factory()->create(['predictive_model_id' => $model->id, 'error_percent' => 8.0, 'forecast_date' => now()->addDays(2)]);

    expect($model->forecastAccuracy())->toBe(6.0);
});

// ── Forecast model unit tests ─────────────────────────────────────────────────

test('Forecast isAccurate returns true when error_percent below threshold', function () {
    $forecast = Forecast::factory()->create(['error_percent' => 5.0, 'forecast_date' => now()->addDay()]);
    expect($forecast->isAccurate(10.0))->toBeTrue();
});

test('Forecast isAccurate returns false when error_percent above threshold', function () {
    $forecast = Forecast::factory()->create(['error_percent' => 15.0, 'forecast_date' => now()->addDay()]);
    expect($forecast->isAccurate(10.0))->toBeFalse();
});

test('Forecast isAccurate returns true when error_percent is null', function () {
    $forecast = Forecast::factory()->create(['error_percent' => null, 'forecast_date' => now()->addDay()]);
    expect($forecast->isAccurate())->toBeTrue();
});

test('Forecast confidenceRange equals upper minus lower', function () {
    $forecast = Forecast::factory()->create([
        'lower_bound' => 900.0,
        'upper_bound' => 1100.0,
        'forecast_date' => now()->addDay(),
    ]);
    expect($forecast->confidenceRange())->toBe(200.0);
});

test('Forecast confidenceRange returns zero when bounds null', function () {
    $forecast = Forecast::factory()->create([
        'lower_bound' => null,
        'upper_bound' => null,
        'forecast_date' => now()->addDay(),
    ]);
    expect($forecast->confidenceRange())->toBe(0.0);
});

// ── BiAnomaly model unit tests ────────────────────────────────────────────────

test('BiAnomaly isCritical returns true for critical severity', function () {
    $anomaly = BiAnomaly::factory()->create(['severity' => 'critical']);
    expect($anomaly->isCritical())->toBeTrue();
});

test('BiAnomaly isCritical returns false for non-critical severity', function () {
    $anomaly = BiAnomaly::factory()->create(['severity' => 'high']);
    expect($anomaly->isCritical())->toBeFalse();
});

test('BiAnomaly isNew returns true when status is new', function () {
    $anomaly = BiAnomaly::factory()->create(['status' => 'new']);
    expect($anomaly->isNew())->toBeTrue();
});

test('BiAnomaly isNew returns false when status is not new', function () {
    $anomaly = BiAnomaly::factory()->create(['status' => 'acknowledged']);
    expect($anomaly->isNew())->toBeFalse();
});

test('BiAnomaly acknowledge updates status and timestamps', function () {
    $user = User::factory()->create();
    $anomaly = BiAnomaly::factory()->create(['status' => 'new']);

    $result = $anomaly->acknowledge($user->id);

    expect($result->status)->toBe('acknowledged')
        ->and($result->acknowledged_by)->toBe($user->id)
        ->and($result->acknowledged_at)->not->toBeNull();
});

// ── PredictiveAnalyticsService: linear regression ────────────────────────────

test('trainLinearRegression stores slope and intercept coefficients', function () {
    $service = predictiveService();
    $model = PredictiveModel::factory()->create();
    $points = linearDataPoints(10, 100.0, 1000.0);

    $trained = $service->trainLinearRegression($model, $points);

    expect($trained->coefficients)->toHaveKeys(['slope', 'intercept'])
        ->and((float) $trained->coefficients['slope'])->toBeGreaterThan(50.0) // close to 100
        ->and($trained->last_trained_at)->not->toBeNull();
});

test('trainLinearRegression computes positive R² accuracy score', function () {
    $service = predictiveService();
    $model = PredictiveModel::factory()->create();
    $points = linearDataPoints(20, 200.0, 5000.0);

    $trained = $service->trainLinearRegression($model, $points);

    expect((float) $trained->accuracy_score)->toBeGreaterThan(0.9);
});

test('trainLinearRegression with perfect linear data gives R² of 1', function () {
    $service = predictiveService();
    $model = PredictiveModel::factory()->create();
    // Perfect linear: y = 50x + 100
    $points = [];
    for ($i = 0; $i < 10; $i++) {
        $points[] = ['date' => now()->subDays(10 - $i)->toDateString(), 'value' => 100.0 + 50.0 * $i];
    }

    $trained = $service->trainLinearRegression($model, $points);

    expect((float) $trained->accuracy_score)->toBeGreaterThanOrEqual(0.9999);
});

// ── PredictiveAnalyticsService: moving average ────────────────────────────────

test('trainMovingAverage stores average and stddev coefficients', function () {
    $service = predictiveService();
    $model = PredictiveModel::factory()->create(['model_type' => 'moving_average']);
    $points = array_map(fn ($i) => ['date' => now()->subDays($i)->toDateString(), 'value' => 1000.0], range(0, 9));

    $trained = $service->trainMovingAverage($model, $points, 7);

    expect($trained->coefficients)->toHaveKeys(['average', 'stddev', 'window_size'])
        ->and((float) $trained->coefficients['average'])->toEqual(1000.0)
        ->and((int) $trained->coefficients['window_size'])->toBe(7);
});

test('trainMovingAverage computes correct average for uniform data', function () {
    $service = predictiveService();
    $model = PredictiveModel::factory()->create(['model_type' => 'moving_average']);
    $points = array_map(fn ($i) => ['date' => now()->subDays($i)->toDateString(), 'value' => 2000.0], range(0, 4));

    $trained = $service->trainMovingAverage($model, $points, 5);

    expect((float) $trained->coefficients['average'])->toEqual(2000.0)
        ->and((float) $trained->coefficients['stddev'])->toEqual(0.0);
});

// ── PredictiveAnalyticsService: generateForecasts ────────────────────────────

test('generateForecasts creates correct number of Forecast records', function () {
    $service = predictiveService();
    $model = PredictiveModel::factory()->create();
    $points = linearDataPoints(10);
    $trained = $service->trainLinearRegression($model, $points);

    $forecasts = $service->generateForecasts($trained, 5);

    expect($forecasts)->toHaveCount(5)
        ->and(Forecast::where('predictive_model_id', $model->id)->count())->toBe(5);
});

test('generateForecasts creates records with lower and upper bounds', function () {
    $service = predictiveService();
    $model = PredictiveModel::factory()->create(['model_type' => 'moving_average']);
    $points = array_map(fn ($i) => ['date' => now()->subDays($i)->toDateString(), 'value' => 1000.0], range(0, 9));
    $trained = $service->trainMovingAverage($model, $points, 7);

    $forecasts = $service->generateForecasts($trained, 3);

    foreach ($forecasts as $forecast) {
        expect($forecast->lower_bound)->not->toBeNull()
            ->and($forecast->upper_bound)->not->toBeNull()
            ->and((float) $forecast->upper_bound)->toBeGreaterThanOrEqual((float) $forecast->lower_bound);
    }
});

test('generateForecasts produces increasing dates', function () {
    $service = predictiveService();
    $model = PredictiveModel::factory()->create();
    $points = linearDataPoints(10);
    $trained = $service->trainLinearRegression($model, $points);

    $forecasts = $service->generateForecasts($trained, 7);

    for ($i = 1; $i < count($forecasts); $i++) {
        expect($forecasts[$i]->forecast_date->gt($forecasts[$i - 1]->forecast_date))->toBeTrue();
    }
});

// ── PredictiveAnalyticsService: anomaly detection ────────────────────────────

test('detectAnomalies creates BiAnomaly records for outliers', function () {
    $service = predictiveService();
    // Normal values around 1000, with one extreme outlier
    $points = [
        ['date' => '2026-01-01', 'value' => 1000],
        ['date' => '2026-01-02', 'value' => 1010],
        ['date' => '2026-01-03', 'value' => 990],
        ['date' => '2026-01-04', 'value' => 1005],
        ['date' => '2026-01-05', 'value' => 1020],
        ['date' => '2026-01-06', 'value' => 980],
        ['date' => '2026-01-07', 'value' => 5000], // extreme outlier
    ];

    $anomalies = $service->detectAnomalies('revenue', $points, 2.0);

    expect($anomalies)->not->toBeEmpty()
        ->and(BiAnomaly::where('entity_type', 'revenue')->count())->toBeGreaterThanOrEqual(1);
});

test('detectAnomalies does not flag normal data', function () {
    $service = predictiveService();
    $points = array_map(
        fn ($i) => ['date' => now()->subDays($i)->toDateString(), 'value' => 1000.0 + rand(-5, 5)],
        range(0, 9)
    );

    // Use a very high threshold to ensure no flags
    $anomalies = $service->detectAnomalies('test_metric', $points, 10.0);

    expect($anomalies)->toBeEmpty();
});

test('detectAnomalies returns empty array for fewer than 2 points', function () {
    $service = predictiveService();
    $anomalies = $service->detectAnomalies('revenue', [['date' => '2026-01-01', 'value' => 1000]], 2.0);

    expect($anomalies)->toBeEmpty();
});

test('detectAnomalies flags the correct outlier date', function () {
    $service = predictiveService();
    $points = [
        ['date' => '2026-01-01', 'value' => 100],
        ['date' => '2026-01-02', 'value' => 102],
        ['date' => '2026-01-03', 'value' => 98],
        ['date' => '2026-01-04', 'value' => 101],
        ['date' => '2026-01-05', 'value' => 99],
        ['date' => '2026-01-06', 'value' => 103],
        ['date' => '2026-01-07', 'value' => 9999], // clear outlier
    ];

    $anomalies = $service->detectAnomalies('sales', $points, 2.0);

    $flaggedDates = array_map(fn ($a) => $a->anomaly_date->toDateString(), $anomalies);
    expect($flaggedDates)->toContain('2026-01-07');
});

// ── PredictiveAnalyticsService: computeGrowthRate ────────────────────────────

test('computeGrowthRate returns correct percentage', function () {
    $service = predictiveService();
    expect($service->computeGrowthRate(1100.0, 1000.0))->toEqual(10.0);
});

test('computeGrowthRate returns negative for decline', function () {
    $service = predictiveService();
    expect($service->computeGrowthRate(900.0, 1000.0))->toEqual(-10.0);
});

test('computeGrowthRate returns zero when previous is zero', function () {
    $service = predictiveService();
    expect($service->computeGrowthRate(500.0, 0.0))->toEqual(0.0);
});

// ── PredictiveAnalyticsService: revenueTrend ─────────────────────────────────

test('revenueTrend returns correct structure', function () {
    $service = predictiveService();
    $result = $service->revenueTrend(6);

    expect($result)->toHaveKeys(['months', 'trend', 'forecast'])
        ->and($result['months'])->toHaveCount(6)
        ->and($result['trend'])->toBeIn(['up', 'down', 'flat'])
        ->and($result['forecast'])->toHaveCount(3);
});

test('revenueTrend months contain required keys', function () {
    $service = predictiveService();
    $result = $service->revenueTrend(3);

    foreach ($result['months'] as $month) {
        expect($month)->toHaveKeys(['month', 'revenue', 'growth_rate']);
    }
});

test('revenueTrend forecast entries have month and forecast_value', function () {
    $service = predictiveService();
    $result = $service->revenueTrend(6);

    foreach ($result['forecast'] as $f) {
        expect($f)->toHaveKeys(['month', 'forecast_value'])
            ->and((float) $f['forecast_value'])->toBeGreaterThanOrEqual(0.0);
    }
});

// Chantier 19 Lot 5: fetchMonthlyRevenue() queried `acc_journal_lines` (a
// table that has never existed anywhere in this app — the real ledger is
// `acc_journal_entries`/`acc_journal_entry_lines`) behind a MySQL-only
// `SHOW TABLES LIKE ...` guard that always throws on this app's sqlite
// driver — both silently caught, so revenueTrend()/growthRates() have
// never once returned real revenue, only synthetic fallback numbers, even
// with real posted revenue in the ledger. Confirmed empirically before the
// fix (this exact scenario returned synthetic ~50000-base data instead of
// the real 12345.67 posted below).
test('revenueTrend surfaces real class-7 (produits) revenue from the real ledger, not synthetic fallback data', function () {
    $client = \Modules\Accounting\Models\ChartOfAccount::factory()->create(['code' => '411', 'type' => 'asset']);
    $ventes = \Modules\Accounting\Models\ChartOfAccount::factory()->create(['code' => '707', 'type' => 'revenue']);

    $entry = \Modules\Accounting\Models\JournalEntry::create([
        'entry_number' => 'BI-TEST-1',
        'date' => now()->toDateString(),
        'entry_date' => now()->toDateString(),
        'description' => 'Vente test BI',
        'status' => 'posted',
        'currency' => 'MGA',
    ]);
    $entry->lines()->create(['account_id' => $client->id, 'debit' => 12345.67, 'credit' => 0]);
    $entry->lines()->create(['account_id' => $ventes->id, 'debit' => 0, 'credit' => 12345.67]);

    $service = predictiveService();
    $result = $service->revenueTrend(3);

    $currentMonth = collect($result['months'])->firstWhere('month', now()->format('Y-m'));

    expect($currentMonth)->not->toBeNull()
        ->and((float) $currentMonth['revenue'])->toBe(12345.67);
});

// ── PredictiveAnalyticsService: getActiveAnomalies ───────────────────────────

test('getActiveAnomalies returns only new anomalies at or above threshold', function () {
    $service = predictiveService();

    BiAnomaly::factory()->create(['severity' => 'low',      'status' => 'new']);
    BiAnomaly::factory()->create(['severity' => 'medium',   'status' => 'new']);
    BiAnomaly::factory()->create(['severity' => 'high',     'status' => 'new']);
    BiAnomaly::factory()->create(['severity' => 'critical', 'status' => 'new']);
    BiAnomaly::factory()->create(['severity' => 'high',     'status' => 'acknowledged']);

    $results = $service->getActiveAnomalies('medium');

    expect($results)->toHaveCount(3); // medium, high, critical — not low, not acknowledged
    foreach ($results as $anomaly) {
        expect($anomaly->status)->toBe('new');
    }
});

test('getActiveAnomalies excludes low severity by default', function () {
    $service = predictiveService();

    BiAnomaly::factory()->create(['severity' => 'low',    'status' => 'new']);
    BiAnomaly::factory()->create(['severity' => 'medium', 'status' => 'new']);

    $results = $service->getActiveAnomalies('medium');
    $severities = $results->pluck('severity')->all();

    expect($severities)->not->toContain('low');
});

// ── API: Predictive Models ────────────────────────────────────────────────────

test('GET /bi/predictive-models returns list', function () {
    actingAsUser('manager');
    PredictiveModel::factory()->count(3)->create();

    $this
        ->getJson('/api/v1/bi/predictive-models')
        ->assertOk()
        ->assertJsonStructure(['data', 'total'])
        ->assertJsonPath('total', 3);
});

test('POST /bi/predictive-models creates a model', function () {
    actingAsUser('manager');

    $this
        ->postJson('/api/v1/bi/predictive-models', [
            'name' => 'Revenue Forecast Model',
            'entity_type' => 'revenue',
            'model_type' => 'linear_regression',
        ])
        ->assertStatus(201)
        ->assertJsonPath('name', 'Revenue Forecast Model')
        ->assertJsonPath('entity_type', 'revenue');
});

test('POST /bi/predictive-models/{model}/train trains a linear regression model', function () {
    actingAsUser('manager');
    $model = PredictiveModel::factory()->create(['model_type' => 'linear_regression']);
    $points = linearDataPoints(10);

    $this
        ->postJson("/api/v1/bi/predictive-models/{$model->id}/train", [
            'data_points' => $points,
        ])
        ->assertOk()
        ->assertJsonPath('model_type', 'linear_regression');

    expect($model->fresh()->coefficients)->toHaveKeys(['slope', 'intercept']);
});

test('POST /bi/predictive-models/{model}/train trains a moving average model', function () {
    actingAsUser('manager');
    $model = PredictiveModel::factory()->create(['model_type' => 'moving_average']);

    $points = array_map(
        fn ($i) => ['date' => now()->subDays($i)->toDateString(), 'value' => 1000.0],
        range(0, 9)
    );

    $this
        ->postJson("/api/v1/bi/predictive-models/{$model->id}/train", [
            'data_points' => $points,
            'periods' => 5,
        ])
        ->assertOk();

    expect($model->fresh()->coefficients)->toHaveKeys(['average', 'stddev']);
});

test('POST /bi/predictive-models/{model}/generate creates forecast records', function () {
    actingAsUser('manager');
    $service = predictiveService();
    $model = PredictiveModel::factory()->create();
    $service->trainLinearRegression($model, linearDataPoints(10));

    $this
        ->postJson("/api/v1/bi/predictive-models/{$model->id}/generate", ['days' => 7])
        ->assertStatus(201)
        ->assertJsonPath('total', 7);
});

test('GET /bi/predictive-models/{model}/forecasts returns forecast list', function () {
    actingAsUser('manager');
    $model = PredictiveModel::factory()->create();
    Forecast::factory()->count(4)->create(['predictive_model_id' => $model->id, 'forecast_date' => now()->addDays(1)]);

    $this
        ->getJson("/api/v1/bi/predictive-models/{$model->id}/forecasts")
        ->assertOk()
        ->assertJsonPath('total', 4);
});

// ── API: Anomalies ────────────────────────────────────────────────────────────

test('GET /bi/anomalies returns list', function () {
    actingAsUser('manager');
    BiAnomaly::factory()->count(3)->create();

    $this
        ->getJson('/api/v1/bi/anomalies')
        ->assertOk()
        ->assertJsonStructure(['data', 'total'])
        ->assertJsonPath('total', 3);
});

test('GET /bi/anomalies filters by severity', function () {
    actingAsUser('manager');
    BiAnomaly::factory()->count(2)->create(['severity' => 'high']);
    BiAnomaly::factory()->count(3)->create(['severity' => 'low']);

    $this
        ->getJson('/api/v1/bi/anomalies?severity=high')
        ->assertOk()
        ->assertJsonPath('total', 2);
});

test('GET /bi/anomalies filters by status', function () {
    actingAsUser('manager');
    BiAnomaly::factory()->count(2)->create(['status' => 'new']);
    BiAnomaly::factory()->count(1)->create(['status' => 'resolved']);

    $this
        ->getJson('/api/v1/bi/anomalies?status=new')
        ->assertOk()
        ->assertJsonPath('total', 2);
});

test('POST /bi/anomalies/detect detects anomalies and returns results', function () {
    actingAsUser('manager');
    $points = [
        ['date' => '2026-01-01', 'value' => 1000],
        ['date' => '2026-01-02', 'value' => 1010],
        ['date' => '2026-01-03', 'value' => 990],
        ['date' => '2026-01-04', 'value' => 1005],
        ['date' => '2026-01-05', 'value' => 980],
        ['date' => '2026-01-06', 'value' => 1020],
        ['date' => '2026-01-07', 'value' => 50000], // extreme outlier
    ];

    $this
        ->postJson('/api/v1/bi/anomalies/detect', [
            'entity_type' => 'revenue',
            'data_points' => $points,
        ])
        ->assertStatus(201)
        ->assertJsonStructure(['data', 'total']);
});

test('POST /bi/anomalies/{anomaly}/acknowledge updates anomaly status', function () {
    actingAsUser('manager');
    $anomaly = BiAnomaly::factory()->create(['status' => 'new']);

    $this
        ->postJson("/api/v1/bi/anomalies/{$anomaly->id}/acknowledge")
        ->assertOk()
        ->assertJsonPath('status', 'acknowledged');
});

// ── API: Analytics ────────────────────────────────────────────────────────────

test('GET /bi/analytics/revenue-trend returns trend structure', function () {
    actingAsUser('manager');

    $this
        ->getJson('/api/v1/bi/analytics/revenue-trend')
        ->assertOk()
        ->assertJsonStructure(['months', 'trend', 'forecast']);
});

test('GET /bi/analytics/growth-rates returns growth rate data', function () {
    actingAsUser('manager');

    $this
        ->getJson('/api/v1/bi/analytics/growth-rates?entity_type=revenue&periods=6')
        ->assertOk()
        ->assertJsonStructure(['data', 'total']);
});

// ── API: Auth guard ───────────────────────────────────────────────────────────

test('unauthenticated request to predictive models returns 401', function () {
    $this->getJson('/api/v1/bi/predictive-models')
        ->assertUnauthorized();
});

test('unauthenticated request to anomalies returns 401', function () {
    $this->getJson('/api/v1/bi/anomalies')
        ->assertUnauthorized();
});

test('unauthenticated request to revenue trend returns 401', function () {
    $this->getJson('/api/v1/bi/analytics/revenue-trend')
        ->assertUnauthorized();
});
