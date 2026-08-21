<?php

use Illuminate\Support\Facades\Route;

// AnalyticsController / RecommendationsController / NLPController / InsightsController
// were never built. Chantier 32.2 (14-layer deep audit) confirmed and closed the
// underlying question this comment used to leave open: PredictiveAnalyticsService,
// RecommendationEngineService, NaturalLanguageProcessingService, and
// AutomatedInsightsService — the 4 services these never-built controllers would have
// exposed — were deleted (see AIServiceProvider::register()'s docblock), not left as
// backlog. Each was either 100% fake demo scaffolding with zero real callers anywhere
// in the repo, or a functional duplicate of a real, live implementation elsewhere in
// the app (Analytics' ForecastingEngineService, BI's own real PredictiveAnalyticsService,
// this module's own real AiAnomalyDetectionService). There is nothing left to route here.
//
// `module:AI` (Chantier 32.2, new): every other business module in this app gates its
// route groups on `module:<Name>` so a tenant admin can toggle it off via the real
// tenant_modules table — this module's routes never had that gate at all, so any
// authenticated user could always reach `/api/v1/ai/*` regardless of whether 'AI' was
// disabled for their tenant. Added for consistency; the frontend `useAiAssistant`
// composable already degrades gracefully on any HTTP failure (including a 403), so this
// closes a real RBAC gap without any frontend change needed.
Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user', 'module:AI'])->prefix('v1/ai')->group(function () {
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
Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user', 'module:AI'])->prefix('v1/ai/admin')->group(function () {
    Route::get('usage',          'Modules\AI\Http\Controllers\Api\AiActionAdvisorController@adminUsage');
    Route::get('limits',         'Modules\AI\Http\Controllers\Api\AiActionAdvisorController@adminListLimits');
    Route::post('limits',        'Modules\AI\Http\Controllers\Api\AiActionAdvisorController@adminSetLimit');
    Route::delete('limits/{id}', 'Modules\AI\Http\Controllers\Api\AiActionAdvisorController@adminDeleteLimit');
});
