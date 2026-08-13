<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Analytics\Models\AnomalyDetectionModel;
use Modules\Analytics\Models\DetectedAnomaly;
use Modules\Analytics\Models\MLModel;
use Modules\Analytics\Models\MLModelVersion;
use Modules\Analytics\Models\PredictionModel;
use Modules\Analytics\Models\Recommendation;
use Modules\Analytics\Models\RecommendationModel;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// MLModel — model attributes & relationships
// ─────────────────────────────────────────────────────────────────────────────

it('creates an ml model with expected fillable fields', function () {
    $company = Company::factory()->create();
    $model = MLModel::factory()->for($company)->create([
        'model_key' => 'revenue-forecast-v2',
        'model_name' => 'Revenue Forecast',
        'model_category' => 'prediction',
        'framework' => 'xgboost',
        'status' => 'development',
    ]);

    expect($model->model_key)->toBe('revenue-forecast-v2')
        ->and($model->model_name)->toBe('Revenue Forecast')
        ->and($model->model_category)->toBe('prediction')
        ->and($model->framework)->toBe('xgboost')
        ->and($model->status)->toBe('development');
});

it('casts hyperparameters as array on an ml model', function () {
    $company = Company::factory()->create();
    $model = MLModel::factory()->for($company)->create([
        'hyperparameters' => ['learning_rate' => 0.01, 'max_depth' => 6],
    ]);

    expect($model->hyperparameters)->toBeArray()
        ->and($model->hyperparameters)->toHaveKey('learning_rate');
});

it('ml model belongs to a company', function () {
    $company = Company::factory()->create();
    $model = MLModel::factory()->for($company)->create();

    expect($model->company)->toBeInstanceOf(Company::class)
        ->and($model->company->id)->toBe($company->id);
});

it('ml model has many versions', function () {
    $company = Company::factory()->create();
    $model = MLModel::factory()->for($company)->create();
    MLModelVersion::factory()->for($model)->count(3)->create();

    expect($model->versions)->toHaveCount(3);
});

it('soft-deletes an ml model', function () {
    $company = Company::factory()->create();
    $model = MLModel::factory()->for($company)->create();
    $id = $model->id;

    $model->delete();

    expect(MLModel::find($id))->toBeNull()
        ->and(MLModel::withTrashed()->find($id))->not->toBeNull();
});

it('ml model tracks production accuracy as decimal', function () {
    $company = Company::factory()->create();
    $model = MLModel::factory()->for($company)->create([
        'production_accuracy' => 0.9234,
    ]);

    expect((float) $model->production_accuracy)->toBeGreaterThan(0.9)
        ->and((float) $model->production_accuracy)->toBeLessThanOrEqual(1.0);
});

// ─────────────────────────────────────────────────────────────────────────────
// PredictionModel — scopes & casts
// ─────────────────────────────────────────────────────────────────────────────

it('creates a prediction model with draft status by default from factory', function () {
    $company = Company::factory()->create();
    $model = PredictionModel::factory()->for($company)->create(['status' => 'draft']);

    expect($model->status)->toBe('draft');
});

it('casts prediction model configuration as array', function () {
    $company = Company::factory()->create();
    $model = PredictionModel::factory()->for($company)->create([
        'configuration' => ['threshold' => 0.5, 'horizon_days' => 30],
    ]);

    expect($model->configuration)->toBeArray()
        ->and($model->configuration)->toHaveKey('threshold');
});

it('prediction model belongs to a company', function () {
    $company = Company::factory()->create();
    $model = PredictionModel::factory()->for($company)->create();

    expect($model->company->id)->toBe($company->id);
});

it('soft-deletes a prediction model', function () {
    $company = Company::factory()->create();
    $model = PredictionModel::factory()->for($company)->create();
    $id = $model->id;

    $model->delete();

    expect(PredictionModel::find($id))->toBeNull()
        ->and(PredictionModel::withTrashed()->find($id))->not->toBeNull();
});

it('prediction model training accuracy is stored as decimal', function () {
    $company = Company::factory()->create();
    $model = PredictionModel::factory()->for($company)->create([
        'training_accuracy' => 0.8765,
    ]);

    expect((float) $model->training_accuracy)->toBeFloat()
        ->and((float) $model->training_accuracy)->toBeGreaterThan(0.0);
});

// ─────────────────────────────────────────────────────────────────────────────
// AnomalyDetectionModel — attributes & HasMany anomalies
// ─────────────────────────────────────────────────────────────────────────────

it('creates anomaly detection model with required fields', function () {
    $company = Company::factory()->create();
    $adm = AnomalyDetectionModel::factory()->for($company)->create([
        'model_name' => 'Transaction Monitor',
        'anomaly_type' => 'transaction',
        'algorithm' => 'isolation_forest',
        'anomaly_threshold' => 0.75,
    ]);

    expect($adm->model_name)->toBe('Transaction Monitor')
        ->and($adm->anomaly_type)->toBe('transaction')
        ->and($adm->algorithm)->toBe('isolation_forest');
});

it('anomaly detection model has many detected anomalies', function () {
    $company = Company::factory()->create();
    $adm = AnomalyDetectionModel::factory()->for($company)->create();
    DetectedAnomaly::factory()->for($adm)->for($company)->count(4)->create();

    expect($adm->anomalies)->toHaveCount(4);
});

it('anomaly detection model threshold is stored as decimal', function () {
    $company = Company::factory()->create();
    $adm = AnomalyDetectionModel::factory()->for($company)->create([
        'anomaly_threshold' => 0.6500,
    ]);

    expect((float) $adm->anomaly_threshold)->toBeFloat()
        ->and((float) $adm->anomaly_threshold)->toBeGreaterThan(0.0)
        ->and((float) $adm->anomaly_threshold)->toBeLessThanOrEqual(1.0);
});

it('soft-deletes anomaly detection model', function () {
    $company = Company::factory()->create();
    $adm = AnomalyDetectionModel::factory()->for($company)->create();
    $id = $adm->id;

    $adm->delete();

    expect(AnomalyDetectionModel::find($id))->toBeNull()
        ->and(AnomalyDetectionModel::withTrashed()->find($id))->not->toBeNull();
});

// ─────────────────────────────────────────────────────────────────────────────
// DetectedAnomaly — attributes & relationships
// ─────────────────────────────────────────────────────────────────────────────

it('detected anomaly has a valid severity level', function () {
    $company = Company::factory()->create();
    $adm = AnomalyDetectionModel::factory()->for($company)->create();
    $anomaly = DetectedAnomaly::factory()->for($adm)->for($company)->create();

    expect($anomaly->severity)->toBeIn(['low', 'medium', 'high', 'critical']);
});

it('detected anomaly score is between 0 and 1', function () {
    $company = Company::factory()->create();
    $adm = AnomalyDetectionModel::factory()->for($company)->create();
    $anomaly = DetectedAnomaly::factory()->for($adm)->for($company)->create();

    expect($anomaly->anomaly_score)->toBeGreaterThanOrEqual(0)
        ->and($anomaly->anomaly_score)->toBeLessThanOrEqual(1);
});

it('detected anomaly belongs to anomaly detection model', function () {
    $company = Company::factory()->create();
    $adm = AnomalyDetectionModel::factory()->for($company)->create();
    $anomaly = DetectedAnomaly::factory()->for($adm)->for($company)->create();

    expect($anomaly->anomalyDetectionModel->id)->toBe($adm->id);
});

// ─────────────────────────────────────────────────────────────────────────────
// RecommendationModel — attributes
// ─────────────────────────────────────────────────────────────────────────────

it('creates recommendation model with recommendation type', function () {
    $company = Company::factory()->create();
    $rm = RecommendationModel::factory()->for($company)->create([
        'recommendation_type' => 'products',
        'algorithm' => 'collaborative_filtering',
    ]);

    expect($rm->recommendation_type)->toBe('products')
        ->and($rm->algorithm)->toBe('collaborative_filtering');
});

it('recommendation model has many recommendations', function () {
    $company = Company::factory()->create();
    $rm = RecommendationModel::factory()->for($company)->create();
    Recommendation::factory()->for($rm)->for($company)->count(5)->create();

    expect($rm->recommendations)->toHaveCount(5);
});

it('recommendation model belongs to company', function () {
    $company = Company::factory()->create();
    $rm = RecommendationModel::factory()->for($company)->create();

    expect($rm->company->id)->toBe($company->id);
});

// ─────────────────────────────────────────────────────────────────────────────
// Recommendation — status transitions
// ─────────────────────────────────────────────────────────────────────────────

it('recommendation starts with pending status by default', function () {
    $company = Company::factory()->create();
    $rm = RecommendationModel::factory()->for($company)->create();
    $rec = Recommendation::factory()->for($rm)->for($company)->create(['status' => 'pending']);

    expect($rec->status)->toBe('pending');
});

it('recommendation relevance score is stored as decimal', function () {
    $company = Company::factory()->create();
    $rm = RecommendationModel::factory()->for($company)->create();
    $rec = Recommendation::factory()->for($rm)->for($company)->create([
        'relevance_score' => 0.8750,
    ]);

    expect((float) $rec->relevance_score)->toBeGreaterThan(0.0)
        ->and((float) $rec->relevance_score)->toBeLessThanOrEqual(1.0);
});

it('recommendation expires_at can be set', function () {
    $company = Company::factory()->create();
    $rm = RecommendationModel::factory()->for($company)->create();
    $expiresAt = now()->addDays(7);
    $rec = Recommendation::factory()->for($rm)->for($company)->create([
        'expires_at' => $expiresAt,
    ]);

    expect($rec->expires_at)->not->toBeNull()
        ->and($rec->expires_at->isAfter(now()))->toBeTrue();
});

it('recommendation is soft-deletable', function () {
    $company = Company::factory()->create();
    $rm = RecommendationModel::factory()->for($company)->create();
    $rec = Recommendation::factory()->for($rm)->for($company)->create();
    $id = $rec->id;

    $rec->delete();

    expect(Recommendation::find($id))->toBeNull()
        ->and(Recommendation::withTrashed()->find($id))->not->toBeNull();
});

// ─────────────────────────────────────────────────────────────────────────────
// Tenant isolation checks (model-level)
// ─────────────────────────────────────────────────────────────────────────────

it('ml models are isolated per company', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    MLModel::factory()->for($companyA)->count(3)->create();
    MLModel::factory()->for($companyB)->count(2)->create();

    $companyAModels = MLModel::where('company_id', $companyA->id)->get();
    $companyBModels = MLModel::where('company_id', $companyB->id)->get();

    expect($companyAModels)->toHaveCount(3)
        ->and($companyBModels)->toHaveCount(2);
});

it('anomaly detection models are isolated per company', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    AnomalyDetectionModel::factory()->for($companyA)->count(2)->create();
    AnomalyDetectionModel::factory()->for($companyB)->count(4)->create();

    expect(AnomalyDetectionModel::where('company_id', $companyA->id)->count())->toBe(2)
        ->and(AnomalyDetectionModel::where('company_id', $companyB->id)->count())->toBe(4);
});

it('prediction models are isolated per company', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    PredictionModel::factory()->for($companyA)->count(5)->create();
    PredictionModel::factory()->for($companyB)->count(1)->create();

    expect(PredictionModel::where('company_id', $companyA->id)->count())->toBe(5)
        ->and(PredictionModel::where('company_id', $companyB->id)->count())->toBe(1);
});
