<?php

use Illuminate\Support\Facades\Route;
use Modules\BI\Http\Controllers\Web\AlertRuleWebController;
use Modules\BI\Http\Controllers\Web\BiWebController;
use Modules\BI\Http\Controllers\Web\DataStoryWebController;
use Modules\BI\Http\Controllers\Web\ExternalDataSourceWebController;
use Modules\BI\Http\Controllers\Web\ForecastingWebController;

Route::middleware(['auth', 'module:BI'])->group(function () {
    Route::get('/bi', [BiWebController::class, 'index'])->name('bi.index');
    Route::get('/bi/analytics', [BiWebController::class, 'analytics'])->name('bi.analytics');
    Route::get('/bi/kpis', [BiWebController::class, 'kpisPage'])->name('bi.kpis');
    Route::get('/bi/nl-query', [BiWebController::class, 'nlQuery'])->name('bi.nl-query');
    Route::get('/bi/visualizations', [BiWebController::class, 'visualizations'])->name('bi.visualizations');
    Route::get('/bi/ai-narratives', [BiWebController::class, 'aiNarratives'])->name('bi.ai-narratives');
    Route::get('/bi/predictive-analytics', [BiWebController::class, 'predictiveAnalytics'])->name('bi.predictive-analytics');
    Route::get('/bi/reports', [BiWebController::class, 'reports'])->name('bi.reports');
    Route::get('/bi/sql-editor', [BiWebController::class, 'sqlEditor'])->name('bi.sql-editor');
    Route::get('/bi/alerts', [BiWebController::class, 'alerts'])->name('bi.alerts');
    Route::get('/bi/data-sources', [BiWebController::class, 'dataSources'])->name('bi.data-sources');

    // Chantier 8.2 — previously orphaned subsystems (real backend + policy,
    // just never routed).
    Route::get('/bi/alert-rules', [AlertRuleWebController::class, 'index'])->name('bi.alert-rules');
    Route::get('/bi/data-stories', [DataStoryWebController::class, 'index'])->name('bi.data-stories');
    Route::get('/bi/external-data-sources', [ExternalDataSourceWebController::class, 'index'])->name('bi.external-data-sources');
    Route::get('/bi/forecasting', [ForecastingWebController::class, 'index'])->name('bi.forecasting');

    // Static segments must be registered before the {dashboard} wildcard.
    Route::get('/bi/dashboards/builder', [BiWebController::class, 'builder'])->name('bi.dashboards.builder');
    Route::get('/bi/dashboards/{dashboard}/builder', [BiWebController::class, 'builder'])->name('bi.dashboards.edit-builder');
    Route::get('/bi/dashboards/{dashboard}', [BiWebController::class, 'dashboardShow'])->name('bi.dashboards.show');
});
