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
use Modules\Strategy\Http\Controllers\Api\StrategyReportExportController;

// Chantier 8.6: this entire group had no module:/role: gate at all — only
// auth:sanctum, session.security, tenancy.user — the same RBAC hole already
// fixed in Inventory/Logistics/HR/Setup/etc. `finance-manager` is the role
// that actually wildcard-matches every `strategy.*` permission in the
// seeder; `employee`/`manager`/`admin` get every non-delete/every permission
// respectively by this app's broad role design (see RolesAndPermissionsSeeder).
Route::prefix('v1/strategy')->middleware('auth:sanctum', 'session.security', 'tenancy.user', 'module:Strategy', 'role:employee,finance-manager,manager,admin')->group(function () {
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
    // {type} is a composite "Module/Model" string (e.g. "Accounting/Invoice"), so it must
    // be allowed to span an extra path segment via a permissive regex constraint.
    Route::get('resource/{type}/{id}', [StrategyObjectiveLinkController::class, 'getResourceHierarchy'])
        ->where('type', '.*')->where('id', '[0-9]+');
    Route::post('objective-links/link', [StrategyObjectiveLinkController::class, 'link']);
    Route::delete('objective-links/{id}', [StrategyObjectiveLinkController::class, 'unlinkById']);
    Route::delete('resource/{type}/{id}', [StrategyObjectiveLinkController::class, 'unlink'])
        ->where('type', '.*')->where('id', '[0-9]+');
    Route::put('objective-links/{id}', [StrategyObjectiveLinkController::class, 'updateContribution']);
    Route::post('objective-links/bulk-link', [StrategyObjectiveLinkController::class, 'bulkLink']);
    Route::get('objective/{id}/links', [StrategyObjectiveLinkController::class, 'getLinkedResources']);
    Route::get('objective/{id}/aggregated', [StrategyObjectiveLinkController::class, 'getAggregatedContribution']);

    // Cascade alignment map (Item #38)
    Route::get('cascade', [CascadeController::class, 'index']);

    // Chantier 29 — rapport de pilotage stratégique (PDF/Excel export).
    Route::get('executive-report/export/pdf', [StrategyReportExportController::class, 'pdf']);
    Route::get('executive-report/export/excel', [StrategyReportExportController::class, 'excel']);

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
Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user', 'module:Strategy', 'role:employee,finance-manager,manager,admin'])->prefix('v1/strategy')->group(function () {
    Route::post('ai/assist', [\Modules\Strategy\Http\Controllers\Api\StrategyAiAssistController::class, 'assist'])
        ->name('strategy.ai.assist');
});
