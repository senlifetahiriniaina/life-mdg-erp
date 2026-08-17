<?php

use Illuminate\Support\Facades\Route;
use Modules\Analytics\Http\Controllers\Api\ForecastingController;
use Modules\Analytics\Http\Controllers\MLModelController;
use Modules\Analytics\Http\Controllers\PredictionController;
use Modules\Analytics\Http\Controllers\RecommendationController;

// ── Phase 41 : Moteur de prévision IA ──────────────────────────────────────
Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user'])->prefix('v1/forecasting')->group(function () {
    // Modèles de prévision
    Route::get('models', [ForecastingController::class, 'indexModels']);
    Route::post('models', [ForecastingController::class, 'storeModel']);
    Route::get('models/{id}', [ForecastingController::class, 'showModel']);
    Route::put('models/{id}', [ForecastingController::class, 'updateModel']);
    Route::post('models/{id}/train', [ForecastingController::class, 'trainModel']);
    Route::get('models/{id}/predictions', [ForecastingController::class, 'modelPredictions']);

    // Alertes
    Route::get('alerts', [ForecastingController::class, 'indexAlerts']);
    Route::post('alerts/{id}/acknowledge', [ForecastingController::class, 'acknowledgeAlert']);

    // Scénarios
    Route::get('scenarios', [ForecastingController::class, 'indexScenarios']);
    Route::post('scenarios', [ForecastingController::class, 'storeScenario']);
    Route::get('scenarios/compare', [ForecastingController::class, 'compareScenarios']);

    // Prévisions spécialisées
    Route::get('demand/{productId}', [ForecastingController::class, 'demandForecast']);
    Route::get('cashflow', [ForecastingController::class, 'cashflowForecast']);
    Route::get('hr/headcount', [ForecastingController::class, 'hrHeadcountForecast']);
    Route::get('hr/turnover-risk', [ForecastingController::class, 'turnoverRisk']);
    Route::get('production', [ForecastingController::class, 'productionForecast']);

    // Analyse IA libre
    Route::post('ai/analyze', [ForecastingController::class, 'aiAnalyze']);

    // AI Narrative (Phase 41)
    Route::post('ai/narrative', [ForecastingController::class, 'aiNarrative']);

    // Hub summary (all 4 modules + alert count)
    Route::get('hub', [ForecastingController::class, 'hub']);

    // Consolidated demand + HR routes
    Route::get('demand', [ForecastingController::class, 'demandForecastByParam']);
    Route::get('hr', [ForecastingController::class, 'hrForecast']);
});

Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user'])->prefix('v1/analytics')->group(function () {
    // Prediction Models (5 endpoints)
    Route::apiResource('predictions', PredictionController::class)
        ->parameters(['predictions' => 'prediction_model']);
    Route::post('predictions/{predictionModel}/train', [PredictionController::class, 'train']);
    Route::get('predictions/{predictionModel}/results', [PredictionController::class, 'results']);

    // Recommendations (6 endpoints)
    // NOTE: for-user must be registered before the apiResource's GET
    // recommendations/{recommendation} — otherwise the wildcard route
    // greedily matches "for-user" as a model id and 404s on binding failure.
    Route::get('recommendations/for-user', [RecommendationController::class, 'forUser']);
    Route::apiResource('recommendations', RecommendationController::class);
    Route::post('recommendations/{recommendation}/act', [RecommendationController::class, 'act']);
    Route::post('recommendations/{recommendation}/dismiss', [RecommendationController::class, 'dismiss']);

    // NOTE: Analytics' own anomaly-detection model registry (isolation_forest/LOF/
    // Mahalanobis config CRUD + detected-anomaly triage) was removed as an orphaned
    // duplicate — zero real callers (no frontend page, no other module) besides its
    // own tests, and its schema never matched the model's $fillable (NOT NULL `name`
    // column nobody wrote to). The ERP's real, working, routed anomaly detection is
    // Modules\AI\Http\Controllers\Api\AiAnomalyController (POST/GET/DELETE
    // /api/v1/ai/anomalies*, backed by AiAnomalyDetectionService, which actually
    // checks Inventory/Accounting/HR for real anomalies). Use that instead.

    // ML Models (7 endpoints)
    Route::apiResource('ml-models', MLModelController::class, ['as' => 'ml_model']);
    Route::get('ml-models/{mlModel}/versions', [MLModelController::class, 'versions']);
    Route::post('ml-models/{mlModel}/deploy', [MLModelController::class, 'deploy']);
    Route::post('ml-models/{mlModel}/rollback', [MLModelController::class, 'rollback']);
    Route::get('ml-models/{mlModel}/ab-tests', [MLModelController::class, 'abTests']);
});

// ── AI Assisted First — Contextual AI guidance ────────────────────────────
Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user'])->prefix('v1/analytics')->group(function () {
    Route::post('ai/assist', [\Modules\Analytics\Http\Controllers\Api\AnalyticsAiAssistController::class, 'assist'])
        ->name('analytics.ai.assist');
});
