<?php

use Illuminate\Support\Facades\Route;
use Modules\BI\Http\Controllers\Api\AiBiController;
use Modules\BI\Http\Controllers\Api\AlertController;
use Modules\BI\Http\Controllers\Api\EmbedController;
use Modules\BI\Http\Controllers\Api\AnalyticsController;
use Modules\BI\Http\Controllers\Api\BiAIController;
use Modules\BI\Http\Controllers\Api\BiInsightsController;
use Modules\BI\Http\Controllers\Api\BiNlQueryController;
use Modules\BI\Http\Controllers\Api\DashboardController;
use Modules\BI\Http\Controllers\Api\DataSourceController;
use Modules\BI\Http\Controllers\Api\DrillDownController;
use Modules\BI\Http\Controllers\Api\ExportController;
use Modules\BI\Http\Controllers\Api\KpiAlertController;
use Modules\BI\Http\Controllers\Api\KpiController;
use Modules\BI\Http\Controllers\Api\PredictiveAnalyticsController;
use Modules\BI\Http\Controllers\Api\QueryController;
use Modules\BI\Http\Controllers\Api\ReportController;

// Default: Simple GET throttle (1000 req/min) with module/role checks
Route::middleware(['auth:sanctum', 'session.security', 'module:BI', 'role:manager,admin', 'throttle:simple_get'])->prefix('v1')->group(function () {
    // Queries - reads
    Route::get('bi/queries', [QueryController::class, 'index']);
    Route::get('bi/queries/{query}', [QueryController::class, 'show']);
    Route::get('bi/queries/{query}/export', [QueryController::class, 'export']);

    // Alerts - reads
    Route::get('bi/alerts', [AlertController::class, 'index']);
    Route::get('bi/alerts/{alert}', [AlertController::class, 'show']);

    // Data Sources - reads
    Route::get('bi/data-sources', [DataSourceController::class, 'index']);
    Route::get('bi/data-sources/{biDataSource}', [DataSourceController::class, 'show']);

    // Dashboards - reads
    Route::get('bi/dashboards', [DashboardController::class, 'index']);
    Route::get('bi/dashboards/{dashboard}', [DashboardController::class, 'show']);

    // Reports - reads
    Route::get('bi/reports', [ReportController::class, 'index']);
    Route::get('bi/reports/{report}', [ReportController::class, 'show']);

    // KPIs - reads
    Route::get('bi/kpis', [KpiController::class, 'index']);
    Route::get('bi/kpis/{kpi}', [KpiController::class, 'show']);

    // KPI Alerts - reads (static routes must come before wildcard routes)
    Route::get('bi/kpi-alerts/unacknowledged', [KpiAlertController::class, 'unacknowledgedEvents']);
    Route::get('bi/kpi-alerts', [KpiAlertController::class, 'index']);
    Route::get('bi/kpi-alerts/{kpiAlert}', [KpiAlertController::class, 'show']);
    Route::get('bi/kpi-alerts/{kpiAlert}/events', [KpiAlertController::class, 'events']);

    // Scheduled Reports - reads
    Route::get('bi/scheduled-reports', [KpiAlertController::class, 'indexScheduled']);
    Route::get('bi/scheduled-reports/{scheduledReport}', [KpiAlertController::class, 'showScheduled']);

    // Insights - reads
    Route::get('bi/insights', [BiInsightsController::class, 'index']);

    // Complex analytics queries
    Route::middleware('throttle:complex_get')->group(function () {
        Route::get('bi/analytics/summary', [AnalyticsController::class, 'summary']);
        Route::post('bi/queries/{query}/run', [QueryController::class, 'run']);
        Route::post('bi/reports/{report}/run', [ReportController::class, 'run']);
        Route::get('bi/export/{dataset}', [ExportController::class, 'download']);
        Route::get('bi/dashboards/{dashboard}/export', [ExportController::class, 'exportDashboard']);
        Route::get('bi/widgets/{widget}/export', [ExportController::class, 'exportWidget']);
        Route::post('bi/widgets/{widget}/drill', [DrillDownController::class, 'drill']);
        Route::get('bi/predictive-models/{model}/forecasts', [PredictiveAnalyticsController::class, 'forecasts']);
        Route::get('bi/analytics/revenue-trend', [PredictiveAnalyticsController::class, 'revenueTrend']);
        Route::get('bi/analytics/growth-rates', [PredictiveAnalyticsController::class, 'growthRates']);
    });

    // Mutations - standard
    Route::middleware('throttle:create_post')->group(function () {
        Route::post('bi/queries', [QueryController::class, 'store']);
        Route::put('bi/queries/{query}', [QueryController::class, 'update']);
        Route::delete('bi/queries/{query}', [QueryController::class, 'destroy']);
        Route::middleware('role:admin,manager')->post('bi/queries/run-raw', [QueryController::class, 'runRaw']);

        Route::post('bi/alerts', [AlertController::class, 'store']);
        Route::put('bi/alerts/{alert}', [AlertController::class, 'update']);
        Route::delete('bi/alerts/{alert}', [AlertController::class, 'destroy']);
        Route::post('bi/alerts/{alert}/test', [AlertController::class, 'test']);

        Route::post('bi/data-sources', [DataSourceController::class, 'store']);
        Route::put('bi/data-sources/{biDataSource}', [DataSourceController::class, 'update']);
        Route::delete('bi/data-sources/{biDataSource}', [DataSourceController::class, 'destroy']);
        Route::post('bi/data-sources/{biDataSource}/test', [DataSourceController::class, 'test']);
        Route::post('bi/data-sources/{biDataSource}/sync', [DataSourceController::class, 'sync']);
        Route::get('bi/data-sources/{biDataSource}/schema', [DataSourceController::class, 'schema']);
        Route::get('bi/data-sources/types', [DataSourceController::class, 'types']);

        Route::post('bi/dashboards', [DashboardController::class, 'store']);
        Route::put('bi/dashboards/{dashboard}', [DashboardController::class, 'update']);
        Route::delete('bi/dashboards/{dashboard}', [DashboardController::class, 'destroy']);

        Route::post('bi/reports', [ReportController::class, 'store']);
        Route::put('bi/reports/{report}', [ReportController::class, 'update']);
        Route::delete('bi/reports/{report}', [ReportController::class, 'destroy']);

        Route::post('bi/kpis', [KpiController::class, 'store']);
        Route::put('bi/kpis/{kpi}', [KpiController::class, 'update']);
        Route::delete('bi/kpis/{kpi}', [KpiController::class, 'destroy']);

        Route::post('bi/kpi-alerts/check', [KpiAlertController::class, 'check']);
        Route::post('bi/kpi-alerts', [KpiAlertController::class, 'store']);
        Route::put('bi/kpi-alerts/{kpiAlert}', [KpiAlertController::class, 'update']);
        Route::delete('bi/kpi-alerts/{kpiAlert}', [KpiAlertController::class, 'destroy']);
        Route::post('bi/kpi-alerts/{kpiAlert}/evaluate', [KpiAlertController::class, 'evaluate']);
        Route::post('bi/alert-events/{alertEvent}/acknowledge', [KpiAlertController::class, 'acknowledgeEvent']);

        Route::post('bi/scheduled-reports', [KpiAlertController::class, 'storeScheduled']);
        Route::put('bi/scheduled-reports/{scheduledReport}', [KpiAlertController::class, 'updateScheduled']);
        Route::delete('bi/scheduled-reports/{scheduledReport}', [KpiAlertController::class, 'destroyScheduled']);
        Route::post('bi/scheduled-reports/{scheduledReport}/send', [KpiAlertController::class, 'sendScheduled']);
        Route::post('bi/scheduled-reports/process-due', [KpiAlertController::class, 'processDueReports']);

        Route::post('bi/predictive-models', [PredictiveAnalyticsController::class, 'store']);
        Route::post('bi/anomalies/{anomaly}/acknowledge', [PredictiveAnalyticsController::class, 'acknowledge']);
    });

    // Advanced AI BI Analysis
    Route::middleware('throttle:expensive')->group(function () {
        Route::post('bi/ai/narrative', [AiBiController::class, 'narrative']);
        Route::post('bi/ai/analyze-objectives', [AiBiController::class, 'analyzeObjectives']);
        Route::post('bi/ai/forecast-compare', [AiBiController::class, 'forecastCompare']);
        Route::post('bi/ai/suggest-alignment', [AiBiController::class, 'suggestAlignment']);
    });
    Route::middleware('throttle:simple_get')->group(function () {
        Route::post('bi/ai/detect-deviations', [AiBiController::class, 'detectDeviations']);
        Route::get('bi/forecast-sources', [AiBiController::class, 'forecastSources']);
        Route::get('bi/objective-catalog', [AiBiController::class, 'objectiveCatalog']);
        Route::get('bi/widgets/{widget}/objectives', [AiBiController::class, 'widgetObjectives']);
    });
    Route::middleware('throttle:create_post')->group(function () {
        Route::post('bi/widgets/{widget}/link-objective', [AiBiController::class, 'linkObjective']);
        Route::post('bi/widgets/{widget}/link-forecast', [AiBiController::class, 'linkForecast']);
    });

    // Expensive operations (AI, predictive models, anomaly detection)
    Route::middleware('throttle:expensive')->group(function () {
        Route::post('bi/nl-query', [BiNlQueryController::class, 'query']);
        Route::post('bi/ai/insights', [BiAIController::class, 'generateInsights']);
        Route::post('bi/ai/detect-trends', [BiAIController::class, 'detectTrends']);
        Route::post('bi/ai/suggest-kpis', [BiAIController::class, 'suggestKpis']);
        Route::post('bi/ai/recommend-dashboard', [BiAIController::class, 'recommendDashboard']);
        Route::post('bi/predictive-models/{model}/train', [PredictiveAnalyticsController::class, 'train']);
        Route::post('bi/predictive-models/{model}/generate', [PredictiveAnalyticsController::class, 'generate']);
        Route::post('bi/anomalies/detect', [PredictiveAnalyticsController::class, 'detectAnomalies']);
    });

    // Predictive models - reads with complex throttle
    Route::middleware('throttle:complex_get')->get('bi/predictive-models', [PredictiveAnalyticsController::class, 'index']);
    Route::middleware('throttle:complex_get')->get('bi/anomalies', [PredictiveAnalyticsController::class, 'listAnomalies']);
});

// ── AI Assisted First — Contextual AI guidance ────────────────────────────
Route::middleware(['auth:sanctum', 'session.security'])->prefix('v1/bi')->group(function () {
    Route::post('ai/assist', [\Modules\BI\Http\Controllers\Api\BIAiAssistController::class, 'assist'])
        ->name('bi.ai.assist');
});

// ── Embed / White-label Analytics ─────────────────────────────────────────
// Authenticated routes (token management)
Route::middleware(['auth:sanctum', 'session.security', 'module:BI', 'role:manager,admin', 'throttle:create_post'])
    ->prefix('v1')
    ->group(function () {
        Route::post('bi/embed/tokens', [EmbedController::class, 'createToken'])
            ->name('bi.embed.tokens.create');
        Route::delete('bi/embed/tokens/{jti}', [EmbedController::class, 'revokeToken'])
            ->name('bi.embed.tokens.revoke');
    });

// Public embed endpoints (no auth — verified by embed token in query string)
Route::prefix('v1')->group(function () {
    Route::get('bi/embed/validate', [EmbedController::class, 'validateToken'])
        ->name('bi.embed.validate');
    Route::get('bi/embed/dashboard/{id}', [EmbedController::class, 'getDashboard'])
        ->where('id', '[0-9]+')
        ->name('bi.embed.dashboard');
});
