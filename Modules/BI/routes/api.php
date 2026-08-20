<?php

use Illuminate\Support\Facades\Route;
use Modules\BI\Http\Controllers\Api\AiBiController;
use Modules\BI\Http\Controllers\Api\AlertController;
use Modules\BI\Http\Controllers\Api\AlertRuleController;
use Modules\BI\Http\Controllers\Api\DataStoryController;
use Modules\BI\Http\Controllers\Api\EmbedController;
use Modules\BI\Http\Controllers\Api\AnalyticsController;
use Modules\BI\Http\Controllers\Api\BiAIController;
use Modules\BI\Http\Controllers\Api\BiInsightsController;
use Modules\BI\Http\Controllers\Api\BiNlQueryController;
use Modules\BI\Http\Controllers\Api\DashboardController;
use Modules\BI\Http\Controllers\Api\DataSourceController;
use Modules\BI\Http\Controllers\Api\DrillDownController;
use Modules\BI\Http\Controllers\Api\ExportController;
use Modules\BI\Http\Controllers\Api\ExternalDataSourceController;
use Modules\BI\Http\Controllers\Api\ForecastingController;
use Modules\BI\Http\Controllers\Api\KpiAlertController;
use Modules\BI\Http\Controllers\Api\KpiController;
use Modules\BI\Http\Controllers\Api\PredictiveAnalyticsController;
use Modules\BI\Http\Controllers\Api\QueryController;
use Modules\BI\Http\Controllers\Api\ReportController;
use Modules\BI\Http\Controllers\Api\VisualizationController;

// Default: Simple GET throttle (1000 req/min) with module/role checks
Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user', 'module:BI', 'role:manager,admin', 'throttle:simple_get'])->prefix('v1')->group(function () {
    // Queries - reads
    Route::get('bi/queries', [QueryController::class, 'index']);
    Route::get('bi/queries/{query}', [QueryController::class, 'show']);
    Route::get('bi/queries/{query}/export', [QueryController::class, 'export']);

    // Alerts - reads
    Route::get('bi/alerts', [AlertController::class, 'index']);
    Route::get('bi/alerts/{alert}', [AlertController::class, 'show']);

    // Data Sources - reads (static routes must come before wildcard routes)
    Route::get('bi/data-sources', [DataSourceController::class, 'index']);
    Route::get('bi/data-sources/types', [DataSourceController::class, 'types']);
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

    // Alert Rules - reads
    Route::get('bi/alert-rules', [AlertRuleController::class, 'index']);
    Route::get('bi/alert-rules/{rule}', [AlertRuleController::class, 'show']);
    Route::get('bi/alert-rules/{rule}/history', [AlertRuleController::class, 'history']);

    // Data Stories - reads
    Route::get('bi/data-stories', [DataStoryController::class, 'index']);
    Route::get('bi/data-stories/{story}', [DataStoryController::class, 'show']);
    Route::get('bi/data-stories/{story}/analytics', [DataStoryController::class, 'analytics']);
    Route::get('bi/data-stories/{story}/views', [DataStoryController::class, 'views']);

    // External Data Sources - reads
    Route::get('bi/external-data-sources', [ExternalDataSourceController::class, 'index']);
    Route::get('bi/external-data-sources/{source}', [ExternalDataSourceController::class, 'show']);
    Route::get('bi/external-data-sources/{source}/sync-history', [ExternalDataSourceController::class, 'syncHistory']);

    // Forecasting (advanced) - reads
    Route::get('bi/forecast-models', [ForecastingController::class, 'index']);
    Route::get('bi/forecast-models/{model}', [ForecastingController::class, 'show']);
    Route::get('bi/forecast-models/{model}/predictions', [ForecastingController::class, 'predictions']);
    Route::get('bi/forecast-models/{model}/scenarios', [ForecastingController::class, 'scenarios']);
    Route::get('bi/forecast-scenarios/{scenario}/predictions', [ForecastingController::class, 'scenarioPredictions']);
    Route::get('bi/forecast-models/{model}/accuracy', [ForecastingController::class, 'accuracy']);
    Route::get('bi/forecast-models/{model}/retraining-logs', [ForecastingController::class, 'retrainingLogs']);

    // Visualizations - reads (static routes must come before wildcard routes)
    Route::get('bi/visualizations', [VisualizationController::class, 'index']);
    Route::get('bi/visualizations/templates', [VisualizationController::class, 'templates']);
    Route::get('bi/visualizations/{visualization}', [VisualizationController::class, 'show']);
    Route::get('bi/visualizations/{visualization}/render', [VisualizationController::class, 'render']);

    // Complex analytics queries
    Route::middleware('throttle:complex_get')->group(function () {
        Route::get('bi/analytics/summary', [AnalyticsController::class, 'summary']);
        Route::post('bi/queries/{query}/run', [QueryController::class, 'run']);
        Route::post('bi/reports/{report}/run', [ReportController::class, 'run']);
        // Chantier 19 Lot 5: the real Reports/Index.vue page calls
        // /generate + /export, neither of which existed — every "Générer
        // maintenant"/"Exporter PDF"/"Exporter Excel" click 404'd.
        Route::post('bi/reports/{report}/generate', [ReportController::class, 'generate']);
        Route::get('bi/reports/{report}/export', [ReportController::class, 'export']);
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

        Route::post('bi/dashboards', [DashboardController::class, 'store']);
        Route::put('bi/dashboards/{dashboard}', [DashboardController::class, 'update']);
        Route::delete('bi/dashboards/{dashboard}', [DashboardController::class, 'destroy']);
        // Chantier 19 Lot 5: Builder.vue (the Dashboard Builder — this
        // module's core feature) has always POSTed here on every save; the
        // route never existed — see WidgetController's own docblock.
        Route::post('bi/dashboards/{dashboard}/widgets', [\Modules\BI\Http\Controllers\Api\WidgetController::class, 'store']);

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

        // Alert Rules - mutations
        Route::post('bi/alert-rules', [AlertRuleController::class, 'store']);
        Route::put('bi/alert-rules/{rule}', [AlertRuleController::class, 'update']);
        Route::delete('bi/alert-rules/{rule}', [AlertRuleController::class, 'destroy']);
        Route::post('bi/alert-rules/{rule}/activate', [AlertRuleController::class, 'activate']);
        Route::post('bi/alert-rules/{rule}/deactivate', [AlertRuleController::class, 'deactivate']);
        Route::post('bi/alert-rules/{rule}/pause', [AlertRuleController::class, 'pause']);
        Route::post('bi/alert-rules/{rule}/conditions', [AlertRuleController::class, 'addCondition']);
        Route::put('bi/alert-rules/{rule}/conditions/{condition}', [AlertRuleController::class, 'updateCondition']);
        Route::delete('bi/alert-rules/{rule}/conditions/{condition}', [AlertRuleController::class, 'deleteCondition']);
        Route::post('bi/alert-rules/{rule}/recipients', [AlertRuleController::class, 'addRecipient']);
        Route::delete('bi/alert-rules/{rule}/recipients/{recipient}', [AlertRuleController::class, 'deleteRecipient']);
        Route::post('bi/alert-rules/{rule}/escalations', [AlertRuleController::class, 'addEscalation']);
        Route::post('bi/alert-history/{alert}/acknowledge', [AlertRuleController::class, 'acknowledgeAlert']);
        Route::post('bi/alert-history/{alert}/resolve', [AlertRuleController::class, 'resolveAlert']);

        // Data Stories - mutations
        Route::post('bi/data-stories', [DataStoryController::class, 'store']);
        Route::put('bi/data-stories/{story}', [DataStoryController::class, 'update']);
        Route::delete('bi/data-stories/{story}', [DataStoryController::class, 'destroy']);
        Route::post('bi/data-stories/{story}/publish', [DataStoryController::class, 'publish']);
        Route::post('bi/data-stories/{story}/archive', [DataStoryController::class, 'archive']);
        Route::post('bi/data-stories/{story}/share', [DataStoryController::class, 'share']);
        Route::post('bi/data-stories/{story}/slides', [DataStoryController::class, 'addSlide']);
        Route::put('bi/data-stories/{story}/slides/{slide}', [DataStoryController::class, 'updateSlide']);
        Route::delete('bi/data-stories/{story}/slides/{slide}', [DataStoryController::class, 'deleteSlide']);
        Route::post('bi/data-stories/{story}/narrative-flows', [DataStoryController::class, 'addNarrativeFlow']);
        Route::put('bi/data-stories/{story}/narrative-flows/{flow}', [DataStoryController::class, 'updateNarrativeFlow']);

        // External Data Sources - mutations
        Route::post('bi/external-data-sources', [ExternalDataSourceController::class, 'store']);
        Route::put('bi/external-data-sources/{source}', [ExternalDataSourceController::class, 'update']);
        Route::delete('bi/external-data-sources/{source}', [ExternalDataSourceController::class, 'delete']);
        Route::post('bi/external-data-sources/{source}/test-connection', [ExternalDataSourceController::class, 'testConnection']);
        Route::post('bi/external-data-sources/{source}/connect', [ExternalDataSourceController::class, 'connect']);
        Route::post('bi/external-data-sources/{source}/disconnect', [ExternalDataSourceController::class, 'disconnect']);
        Route::post('bi/external-data-sources/{source}/credentials', [ExternalDataSourceController::class, 'storeCredential']);
        Route::delete('bi/external-data-sources/{source}/credentials/{credential}', [ExternalDataSourceController::class, 'deleteCredential']);
        Route::post('bi/external-data-sources/{source}/field-mappings', [ExternalDataSourceController::class, 'mapFields']);
        Route::put('bi/external-data-sources/{source}/field-mappings/{mapping}', [ExternalDataSourceController::class, 'updateMapping']);
        Route::delete('bi/external-data-sources/{source}/field-mappings/{mapping}', [ExternalDataSourceController::class, 'deleteMapping']);
        Route::post('bi/external-data-sources/{source}/sync-config', [ExternalDataSourceController::class, 'configureSyncRequest']);
        Route::post('bi/external-data-sources/{source}/sync', [ExternalDataSourceController::class, 'syncNow']);
        Route::post('bi/external-data-sources/{source}/transformation-rules', [ExternalDataSourceController::class, 'addTransformationRule']);
        Route::put('bi/external-data-sources/{source}/transformation-rules/{rule}', [ExternalDataSourceController::class, 'updateTransformationRule']);
        Route::delete('bi/external-data-sources/{source}/transformation-rules/{rule}', [ExternalDataSourceController::class, 'deleteTransformationRule']);

        // Forecasting (advanced) - mutations
        Route::post('bi/forecast-models', [ForecastingController::class, 'store']);
        Route::put('bi/forecast-models/{model}', [ForecastingController::class, 'update']);
        Route::delete('bi/forecast-models/{model}', [ForecastingController::class, 'delete']);
        Route::post('bi/forecast-models/{model}/scenarios', [ForecastingController::class, 'createScenario']);

        // Visualizations - mutations
        Route::post('bi/visualizations', [VisualizationController::class, 'store']);
        Route::put('bi/visualizations/{visualization}', [VisualizationController::class, 'update']);
        Route::delete('bi/visualizations/{visualization}', [VisualizationController::class, 'destroy']);
        Route::post('bi/visualizations/{visualization}/export', [VisualizationController::class, 'export']);
        Route::post('bi/visualizations/{visualization}/share', [VisualizationController::class, 'share']);
        Route::post('bi/visualizations/{visualization}/performance', [VisualizationController::class, 'updatePerformance']);
        Route::post('bi/visualizations/{visualization}/enable-real-time', [VisualizationController::class, 'enableRealTime']);
        Route::post('bi/visualizations/{visualization}/disable-real-time', [VisualizationController::class, 'disableRealTime']);
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
    // NOTE: 'link-objective'/'link-forecast' routes were removed here (Chantier 10) —
    // they pointed at AiBiController::linkObjective()/linkForecast(), neither of
    // which ever existed (a guaranteed "call to undefined method" on every request),
    // and no Vue page or test anywhere in the repo ever called either URL. See
    // CLAUDE.md's Chantier 10 BI entry: AiBiController's objective/forecast-alignment
    // endpoints are canned-response stubs with zero real consumer, documented as a
    // gap rather than built out (would require inventing new link-storage schema and
    // alignment-scoring business logic that was never specified anywhere).

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

        // Forecasting (advanced) - lifecycle
        Route::post('bi/forecast-models/{model}/train', [ForecastingController::class, 'train']);
        Route::post('bi/forecast-models/{model}/deploy', [ForecastingController::class, 'deploy']);
        Route::post('bi/forecast-models/{model}/archive', [ForecastingController::class, 'archive']);
        Route::post('bi/forecast-models/{model}/mark-for-retraining', [ForecastingController::class, 'markForRetraining']);
    });

    // Predictive models - reads with complex throttle
    Route::middleware('throttle:complex_get')->get('bi/predictive-models', [PredictiveAnalyticsController::class, 'index']);
    Route::middleware('throttle:complex_get')->get('bi/anomalies', [PredictiveAnalyticsController::class, 'listAnomalies']);
});

// ── AI Assisted First — Contextual AI guidance ────────────────────────────
Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user'])->prefix('v1/bi')->group(function () {
    Route::post('ai/assist', [\Modules\BI\Http\Controllers\Api\BIAiAssistController::class, 'assist'])
        ->name('bi.ai.assist');
});

// ── Embed / White-label Analytics ─────────────────────────────────────────
// Authenticated routes (token management)
Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user', 'module:BI', 'role:manager,admin', 'throttle:create_post'])
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
