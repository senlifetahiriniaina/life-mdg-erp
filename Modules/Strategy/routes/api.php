<?php

use Illuminate\Support\Facades\Route;
use Modules\Strategy\Http\Controllers\Api\StrategyPlanController;
use Modules\Strategy\Http\Controllers\Api\OkrController;
use Modules\Strategy\Http\Controllers\Api\KpiController;
use Modules\Strategy\Http\Controllers\Api\ScenarioController;
use Modules\Strategy\Http\Controllers\Api\RitualController;
use Modules\Strategy\Http\Controllers\Api\SignalController;
use Modules\Strategy\Http\Controllers\Api\StrategyAdvisorController;
use Modules\Strategy\Http\Controllers\Api\StrategyObjectiveLinkController;
use Modules\Strategy\Http\Controllers\Api\RatioController;
use Modules\Strategy\Http\Controllers\Api\CascadeController;

Route::prefix('v1/strategy')->middleware('auth:sanctum', 'session.security')->group(function () {
    // Plans
    Route::apiResource('plans', StrategyPlanController::class);
    Route::get('plans/{id}/tree', [StrategyPlanController::class, 'tree']);
    Route::post('plans/{id}/duplicate', [StrategyPlanController::class, 'duplicate']);
    Route::get('plans/{id}/health', [StrategyPlanController::class, 'health']);

    // OKRs
    Route::get('objectives/tree', [OkrController::class, 'tree']);
    Route::apiResource('objectives', OkrController::class);
    Route::post('objectives/{id}/cascade', [OkrController::class, 'cascade']);
    Route::post('key-results', [OkrController::class, 'storeKr']);
    Route::put('key-results/{id}', [OkrController::class, 'updateKr']);
    Route::post('key-results/{id}/progress', [OkrController::class, 'updateProgress']);

    // KPIs
    Route::get('kpis/sources', [KpiController::class, 'sources']);
    Route::apiResource('kpis', KpiController::class);
    Route::get('kpis/{id}/values', [KpiController::class, 'values']);
    Route::get('kpis/{id}/refresh', [KpiController::class, 'refresh']);

    // Scenarios
    Route::post('scenarios/compare', [ScenarioController::class, 'compare']);
    Route::apiResource('scenarios', ScenarioController::class);
    Route::post('scenarios/{id}/assumptions', [ScenarioController::class, 'addAssumption']);
    Route::put('scenarios/{id}/assumptions/{aId}', [ScenarioController::class, 'updateAssumption']);
    Route::get('scenarios/{id}/impact', [ScenarioController::class, 'impact']);

    // Rituals
    Route::get('rituals/upcoming', [RitualController::class, 'upcoming']);
    Route::apiResource('rituals', RitualController::class);
    Route::get('rituals/{id}/sessions', [RitualController::class, 'sessions']);
    Route::post('rituals/{id}/sessions', [RitualController::class, 'createSession']);
    Route::put('rituals/sessions/{id}/start', [RitualController::class, 'startSession']);
    Route::put('rituals/sessions/{id}/complete', [RitualController::class, 'completeSession']);

    // Signals
    Route::get('signals', [SignalController::class, 'index']);
    Route::post('signals/refresh', [SignalController::class, 'refresh']);
    Route::put('signals/{id}/read', [SignalController::class, 'markRead']);
    Route::put('signals/{id}/dismiss', [SignalController::class, 'dismiss']);

    // Objective Links (polymorphic resource linking)
    Route::get('resource/{type}/{id}', [StrategyObjectiveLinkController::class, 'getResourceHierarchy']);
    Route::post('objective-links/link', [StrategyObjectiveLinkController::class, 'link']);
    Route::delete('objective-links/{id}', [StrategyObjectiveLinkController::class, 'unlinkById']);
    Route::delete('resource/{type}/{id}', [StrategyObjectiveLinkController::class, 'unlink']);
    Route::put('objective-links/{id}', [StrategyObjectiveLinkController::class, 'updateContribution']);
    Route::post('objective-links/bulk-link', [StrategyObjectiveLinkController::class, 'bulkLink']);
    Route::get('objective/{id}/links', [StrategyObjectiveLinkController::class, 'getLinkedResources']);
    Route::get('objective/{id}/aggregated', [StrategyObjectiveLinkController::class, 'getAggregatedContribution']);

    // Cascade alignment map (Item #38)
    Route::get('cascade', [CascadeController::class, 'index']);

    // Ratios & Benchmarks (Strategy First)
    Route::get('ratios', [RatioController::class, 'index']);
    Route::get('ratios/{module}', [RatioController::class, 'byModule']);
    Route::get('benchmarks', [RatioController::class, 'benchmarks']);
    Route::get('correlations', [RatioController::class, 'correlations']);
    Route::post('ai/recommend', [RatioController::class, 'aiRecommend']);
    Route::get('alerts', [RatioController::class, 'alerts']);

    // AI Advisor
    Route::prefix('advisor')->group(function () {
        Route::get('insights', [StrategyAdvisorController::class, 'insights']);
        Route::post('recommendations', [StrategyAdvisorController::class, 'recommendations']);
        Route::post('board-report', [StrategyAdvisorController::class, 'boardReport']);
        Route::post('ritual-summary', [StrategyAdvisorController::class, 'ritualSummary']);
        Route::post('formulate-okr', [StrategyAdvisorController::class, 'formulateOkr']);
    });
});

// ── AI Assisted First — Contextual AI guidance ────────────────────────────
Route::middleware(['auth:sanctum', 'session.security'])->prefix('v1/strategy')->group(function () {
    Route::post('ai/assist', [\Modules\Strategy\Http\Controllers\Api\StrategyAiAssistController::class, 'assist'])
        ->name('strategy.ai.assist');
});
