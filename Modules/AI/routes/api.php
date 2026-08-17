<?php

use Illuminate\Support\Facades\Route;

// AnalyticsController / RecommendationsController / NLPController / InsightsController
// were never built — the routes below referenced them, but AI's ServiceProvider was
// never actually registered until now (no module.json existed for this module), so
// this was previously dead code with zero effect. Registering the provider makes
// loadRoutesFrom() run for real, which would otherwise crash `route:list` and any
// request to these paths on a class that doesn't exist. The underlying services DO
// exist and are bound in the container (PredictiveAnalyticsService,
// RecommendationEngineService, NaturalLanguageProcessingService,
// AutomatedInsightsService — see AIServiceProvider::register()) — only the HTTP
// controller layer is missing. Left commented out as backlog (building 4 controllers
// / ~24 endpoints is new feature work, not a wiring fix) rather than routed to a
// nonexistent class.
Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user'])->prefix('v1/ai')->group(function () {
    // Contextual AI Assistant (AI Assisted First)
    Route::post('assist', 'Modules\AI\Http\Controllers\Api\AiAssistantController@assist');
    Route::get('assist/modules', 'Modules\AI\Http\Controllers\Api\AiAssistantController@modules');

    // Anomaly Detection
    Route::post('anomalies/detect', 'Modules\AI\Http\Controllers\Api\AiAnomalyController@detect');
    Route::get('anomalies', 'Modules\AI\Http\Controllers\Api\AiAnomalyController@index');
    Route::delete('anomalies/{id}', 'Modules\AI\Http\Controllers\Api\AiAnomalyController@dismiss');

    // Natural Language Search
    Route::post('search', 'Modules\AI\Http\Controllers\Api\AiSearchController@search');

    // Action Advisor
    Route::post('advise',    'Modules\AI\Http\Controllers\Api\AiActionAdvisorController@advise');
    Route::get('usage/me',   'Modules\AI\Http\Controllers\Api\AiActionAdvisorController@myUsage');
});

// Admin AI budget management (separate prefix, same sanctum guard)
Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user'])->prefix('v1/ai/admin')->group(function () {
    Route::get('usage',          'Modules\AI\Http\Controllers\Api\AiActionAdvisorController@adminUsage');
    Route::get('limits',         'Modules\AI\Http\Controllers\Api\AiActionAdvisorController@adminListLimits');
    Route::post('limits',        'Modules\AI\Http\Controllers\Api\AiActionAdvisorController@adminSetLimit');
    Route::delete('limits/{id}', 'Modules\AI\Http\Controllers\Api\AiActionAdvisorController@adminDeleteLimit');
});
