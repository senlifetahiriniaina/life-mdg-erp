<?php

use Illuminate\Support\Facades\Route;
use Modules\CRM\Http\Controllers\Api\AccountController;
use Modules\CRM\Http\Controllers\Api\ActivityController;
use Modules\CRM\Http\Controllers\Api\AiAgentController;
use Modules\CRM\Http\Controllers\Api\CampaignController;
use Modules\CRM\Http\Controllers\Api\ContactController;
use Modules\CRM\Http\Controllers\Api\ContactEmailController;
use Modules\CRM\Http\Controllers\Api\CrmAIController;
use Modules\CRM\Http\Controllers\Api\CrmProspectingController;
use Modules\CRM\Http\Controllers\Api\EinsteinForecastingController;
use Modules\CRM\Http\Controllers\Api\EmailSequenceController;
use Modules\CRM\Http\Controllers\Api\ForecastController;
use Modules\CRM\Http\Controllers\Api\LeadController;
use Modules\CRM\Http\Controllers\Api\OpportunityController;
use Modules\CRM\Http\Controllers\Api\OpportunityHistoryController;
use Modules\CRM\Http\Controllers\Api\OpportunityScoringController;
use Modules\CRM\Http\Controllers\Api\PipelineAnalyticsController;
use Modules\CRM\Http\Controllers\Api\PipelineController;
use Modules\CRM\Http\Controllers\Api\QuoteController;
use Modules\CRM\Http\Controllers\Api\TerritoryController;
use Modules\CRM\Http\Controllers\Api\CallRecordingController;
use Modules\CRM\Http\Controllers\Api\VoipController;
use Modules\CRM\Http\Controllers\Api\WebFormController;
use Modules\CRM\Http\Controllers\Api\RevenueIntelligenceController;

// Public web-form submission — no auth
Route::post('v1/crm/forms/{slug}/submit', [WebFormController::class, 'submit'])
    ->name('crm.forms.submit');

// Default: Simple GET throttle (1000 req/min) — overridden for specific endpoint groups
Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user', 'module:CRM', 'throttle:simple_get'])->prefix('v1')->group(function () {
    // Read-only endpoints with 5-minute cache (GET only)
    Route::middleware('cache.api:5')->group(function () {
        Route::apiResource('crm/contacts', ContactController::class)->only(['index', 'show'])->names('crm.contacts');
        // Chantier 32.15: real, cheap consumer for DuplicateDetectionService (Chantier 10),
        // previously written but never routed anywhere.
        Route::get('crm/contacts/{contact}/duplicates', [ContactController::class, 'duplicates'])->name('crm.contacts.duplicates');
        Route::apiResource('crm/accounts', AccountController::class)->only(['index', 'show'])->names('crm.accounts');
        Route::get('crm/territories/team-quotas', [TerritoryController::class, 'teamQuotas'])->name('crm.territories.team-quotas');
        Route::get('crm/territories/{territory}/forecast', [TerritoryController::class, 'forecast'])->name('crm.territories.forecast');
        Route::get('crm/territories/{territory}/at-risk', [TerritoryController::class, 'atRisk'])->name('crm.territories.at-risk');
        Route::apiResource('crm/campaigns', CampaignController::class)->only(['index', 'show'])->names('crm.campaigns');
        Route::get('crm/campaigns/{campaign}/analytics', [CampaignController::class, 'getAnalytics'])->name('crm.campaigns.analytics');
    });

    // Mutations — no cache (150 req/min)
    Route::middleware('throttle:create_post')->group(function () {
        Route::apiResource('crm/contacts', ContactController::class)->only(['store', 'update', 'destroy'])->names('crm.contacts');
        Route::apiResource('crm/accounts', AccountController::class)->only(['store', 'update', 'destroy'])->names('crm.accounts');

        // Contact Email Communications
        Route::post('crm/contacts/{contact}/email/welcome', [ContactEmailController::class, 'sendWelcome'])->name('crm.contacts.email.welcome');
        Route::post('crm/contacts/{contact}/email/custom', [ContactEmailController::class, 'sendCustom'])->name('crm.contacts.email.custom');
        Route::post('crm/contacts/email/bulk', [ContactEmailController::class, 'sendBulk'])->name('crm.contacts.email.bulk');

        Route::apiResource('crm/campaigns', CampaignController::class)->only(['store', 'update'])->names('crm.campaigns');
        Route::post('crm/campaigns/{campaign}/launch', [CampaignController::class, 'launch'])->name('crm.campaigns.launch');
        Route::post('crm/campaigns/{campaign}/pause', [CampaignController::class, 'pause'])->name('crm.campaigns.pause');
    });

    // Territory mutations (150 req/min)
    Route::middleware('throttle:create_post')->group(function () {
        // Territory — static routes BEFORE apiResource to avoid being caught as {territory} param
        Route::post('crm/territories/auto-assign', [TerritoryController::class, 'autoAssign'])->name('crm.territories.auto-assign');
        Route::post('crm/territories/rebalance', [TerritoryController::class, 'rebalance'])->name('crm.territories.rebalance');
    });

    // Territory read/write mixed
    Route::middleware('cache.api:10')->group(function () {
        Route::apiResource('crm/territories', TerritoryController::class)->only(['index', 'show'])->names('crm.territories');
    });
    Route::middleware('throttle:create_post')->group(function () {
        Route::apiResource('crm/territories', TerritoryController::class)->only(['store', 'update', 'destroy'])->names('crm.territories');
        Route::post('crm/territories/{territory}/assign-opportunity', [TerritoryController::class, 'assignOpportunity'])->name('crm.territories.assign-opportunity');
    });

    // Tier 2: Territory coverage. The rest of the old "territory-management"
    // group (index/forecast/at-risk/auto-balance/quota-distribution) called
    // TerritoryManagementService methods that didn't exist at all, and
    // duplicated functionality TerritoryController already provides for real
    // via TerritoryService/TerritoryForecastService (index, forecast,
    // territoryForecast, atRisk, rebalance, teamQuotas) — deleted rather than
    // repaired, per Chantier 8.2's audit.
    Route::middleware('throttle:complex_get')->get('crm/territory-management/coverage', [TerritoryController::class, 'coverage'])->name('crm.territory-management.coverage');

    // Tier 2: Einstein-style Weighted Forecasting (complex analytics)
    Route::middleware('throttle:complex_get')->prefix('crm/einstein-forecasting')->group(function () {
        Route::get('by-representative', [EinsteinForecastingController::class, 'byRepresentative'])->name('crm.einstein-forecasting.by-rep');
        Route::get('by-product', [EinsteinForecastingController::class, 'byProduct'])->name('crm.einstein-forecasting.by-product');
        Route::get('confidence', [EinsteinForecastingController::class, 'confidence'])->name('crm.einstein-forecasting.confidence');
        Route::get('metrics', [EinsteinForecastingController::class, 'metrics'])->name('crm.einstein-forecasting.metrics');
    });
    Route::middleware('throttle:create_post')->prefix('crm/einstein-forecasting')->group(function () {
        Route::post('generate', [EinsteinForecastingController::class, 'generateForecast'])->name('crm.einstein-forecasting.generate');
        Route::post('adjust', [EinsteinForecastingController::class, 'adjust'])->name('crm.einstein-forecasting.adjust');
    });
    // Opportunities with 5-minute cache for reads
    Route::middleware('cache.api:5')->group(function () {
        Route::get('crm/opportunities/kanban', [OpportunityController::class, 'kanban'])->name('crm.opportunities.kanban');
        Route::get('crm/opportunities/pipeline', [OpportunityController::class, 'pipeline'])->name('crm.opportunities.pipeline');
        Route::apiResource('crm/opportunities', OpportunityController::class)->only(['index', 'show'])->names('crm.opportunities');
        Route::get('crm/opportunities/{opportunity}/history', [OpportunityHistoryController::class, 'index'])->name('crm.opportunities.history');
    });
    Route::middleware('throttle:create_post')->group(function () {
        Route::apiResource('crm/opportunities', OpportunityController::class)->only(['store', 'update', 'destroy'])->names('crm.opportunities');
    });

    // Leads with 5-minute cache
    Route::middleware('cache.api:5')->group(function () {
        Route::apiResource('crm/leads', LeadController::class)->only(['index', 'show'])->names('crm.leads');
    });
    Route::middleware('throttle:create_post')->group(function () {
        Route::apiResource('crm/leads', LeadController::class)->only(['store', 'update', 'destroy'])->names('crm.leads');
    });

    // Activities with 5-minute cache
    Route::middleware('cache.api:5')->group(function () {
        Route::apiResource('crm/activities', ActivityController::class)->only(['index', 'show'])->names('crm.activities');
    });
    Route::middleware('throttle:create_post')->group(function () {
        Route::apiResource('crm/activities', ActivityController::class)->only(['store', 'update', 'destroy'])->names('crm.activities');
    });

    // Pipelines with 15-minute cache (rarely change)
    Route::middleware('cache.api:15')->group(function () {
        Route::apiResource('crm/pipelines', PipelineController::class)->only(['index', 'show'])->names('crm.pipelines');
    });
    Route::middleware('throttle:create_post')->group(function () {
        Route::apiResource('crm/pipelines', PipelineController::class)->only(['store', 'update', 'destroy'])->names('crm.pipelines');
    });

    // Email sequences (legacy) - reads only
    Route::get('crm/sequences', [EmailSequenceController::class, 'index'])->name('crm.sequences.index');
    Route::get('crm/sequences/{sequence}', [EmailSequenceController::class, 'show'])->name('crm.sequences.show');
    Route::middleware('throttle:create_post')->group(function () {
        Route::post('crm/sequences', [EmailSequenceController::class, 'store'])->name('crm.sequences.store');
        Route::put('crm/sequences/{sequence}', [EmailSequenceController::class, 'update'])->name('crm.sequences.update');
        Route::delete('crm/sequences/{sequence}', [EmailSequenceController::class, 'destroy'])->name('crm.sequences.destroy');
        Route::post('crm/sequences/{sequence}/enroll', [EmailSequenceController::class, 'enrollLegacy'])->name('crm.sequences.enroll');
        Route::delete('crm/sequences/enrollments/{enrollment}', [EmailSequenceController::class, 'unenroll'])->name('crm.sequences.unenroll');
    });

    // Email sequences v2 — static routes BEFORE {sequence} wildcard
    Route::middleware('throttle:create_post')->group(function () {
        Route::post('crm/email-sequences/process-due', [EmailSequenceController::class, 'processDue'])->name('crm.email-sequences.process-due');
    });
    Route::get('crm/email-sequences', [EmailSequenceController::class, 'index'])->name('crm.email-sequences.index');
    Route::get('crm/email-sequences/{sequence}', [EmailSequenceController::class, 'show'])->name('crm.email-sequences.show');
    Route::get('crm/email-sequences/{sequence}/steps', [EmailSequenceController::class, 'steps'])->name('crm.email-sequences.steps.index');
    Route::get('crm/email-sequences/{sequence}/enrollments', [EmailSequenceController::class, 'enrollments'])->name('crm.email-sequences.enrollments');
    Route::middleware('throttle:create_post')->group(function () {
        Route::post('crm/email-sequences', [EmailSequenceController::class, 'store'])->name('crm.email-sequences.store');
        Route::put('crm/email-sequences/{sequence}', [EmailSequenceController::class, 'update'])->name('crm.email-sequences.update');
        Route::delete('crm/email-sequences/{sequence}', [EmailSequenceController::class, 'destroy'])->name('crm.email-sequences.destroy');
        Route::post('crm/email-sequences/{sequence}/activate', [EmailSequenceController::class, 'activate'])->name('crm.email-sequences.activate');
        Route::post('crm/email-sequences/{sequence}/pause', [EmailSequenceController::class, 'pause'])->name('crm.email-sequences.pause');
        Route::post('crm/email-sequences/{sequence}/steps', [EmailSequenceController::class, 'addStep'])->name('crm.email-sequences.steps.store');
        Route::put('crm/email-sequences/{sequence}/steps/{step}', [EmailSequenceController::class, 'updateStep'])->name('crm.email-sequences.steps.update');
        Route::delete('crm/email-sequences/{sequence}/steps/{step}', [EmailSequenceController::class, 'deleteStep'])->name('crm.email-sequences.steps.destroy');
        Route::post('crm/email-sequences/{sequence}/enroll', [EmailSequenceController::class, 'enroll'])->name('crm.email-sequences.enroll');
        Route::post('crm/email-sequences/{sequence}/stats', [EmailSequenceController::class, 'stats'])->name('crm.email-sequences.stats');
    });

    // Web forms (admin)
    Route::get('crm/forms', [WebFormController::class, 'index'])->name('crm.forms.index');
    Route::get('crm/forms/{form}', [WebFormController::class, 'show'])->name('crm.forms.show');
    Route::middleware('throttle:create_post')->group(function () {
        Route::post('crm/forms', [WebFormController::class, 'store'])->name('crm.forms.store');
        Route::put('crm/forms/{form}', [WebFormController::class, 'update'])->name('crm.forms.update');
        Route::delete('crm/forms/{form}', [WebFormController::class, 'destroy'])->name('crm.forms.destroy');
    });

    Route::middleware('throttle:ai')->prefix('crm/ai')->group(function () {
        Route::post('score-leads', [CrmAIController::class, 'scoreLeads']);
        Route::post('suggest-next-action', [CrmAIController::class, 'suggestNextAction']);
        Route::post('draft-follow-up', [CrmAIController::class, 'draftFollowUp']);
        Route::post('detect-duplicates', [CrmProspectingController::class, 'detectDuplicates']);
        Route::post('generate-prospecting-email', [CrmProspectingController::class, 'generateEmail']);
        Route::post('draft-prospecting-email', [CrmProspectingController::class, 'generateEmail']);
        Route::post('analyze-sentiment', [CrmProspectingController::class, 'analyzeSentiment']);
        Route::post('transcribe-call', [CrmAIController::class, 'transcribeCall']);
    });

    // Territory Forecasting (Einstein-style, complex reads)
    Route::middleware('throttle:complex_get')->prefix('crm/forecast')->group(function () {
        Route::get('by-territory', [TerritoryController::class, 'territoryForecast'])->name('crm.forecast.by-territory');
        Route::get('territory-comparison', [TerritoryController::class, 'forecastComparison'])->name('crm.forecast.territory-comparison');
    });

    // Opportunity Scoring (Einstein-style)
    Route::middleware('throttle:complex_get')->prefix('crm/scoring')->group(function () {
        // Reads only
        Route::get('leaderboard', [OpportunityScoringController::class, 'leaderboard'])->name('crm.scoring.leaderboard');
        Route::get('forecast', [OpportunityScoringController::class, 'forecast'])->name('crm.scoring.forecast');
        Route::get('rules', [OpportunityScoringController::class, 'indexRules'])->name('crm.scoring.rules.index');
    });
    Route::middleware('throttle:create_post')->prefix('crm/scoring')->group(function () {
        // Writes
        Route::post('score-all', [OpportunityScoringController::class, 'scoreAll'])->name('crm.scoring.score_all');
        Route::post('rules', [OpportunityScoringController::class, 'storeRule'])->name('crm.scoring.rules.store');
        Route::put('rules/{rule}', [OpportunityScoringController::class, 'updateRule'])->name('crm.scoring.rules.update');
        Route::delete('rules/{rule}', [OpportunityScoringController::class, 'destroyRule'])->name('crm.scoring.rules.destroy');
    });

    // v1.6 canonical REST paths for scoring
    Route::get('crm/opportunity-scores', [OpportunityScoringController::class, 'indexScores'])->name('crm.opportunity-scores.index');
    Route::get('crm/scoring-rules', [OpportunityScoringController::class, 'indexRules'])->name('crm.scoring-rules.index');
    Route::middleware('throttle:create_post')->group(function () {
        Route::post('crm/opportunity-scores/bulk', [OpportunityScoringController::class, 'scoreAll'])->name('crm.opportunity-scores.bulk');
        Route::post('crm/scoring-rules', [OpportunityScoringController::class, 'storeRule'])->name('crm.scoring-rules.store');
        Route::put('crm/scoring-rules/{scoringRule}', [OpportunityScoringController::class, 'updateScoringRule'])->name('crm.scoring-rules.update');
        Route::delete('crm/scoring-rules/{scoringRule}', [OpportunityScoringController::class, 'destroyScoringRule'])->name('crm.scoring-rules.destroy');
    });

    // Per-opportunity scoring & signals — Tier 1: Opportunity Scoring
    Route::get('crm/opportunities/{opportunity}/score', [OpportunityScoringController::class, 'showScore'])->name('crm.opportunities.score.show');
    // crm/opportunity-scores itself is already registered above (line ~210,
    // OpportunityScoringController::indexScores, which just delegates to
    // getScores() — this used to be a duplicate registration of the same path
    // under the same route name, which left getScores() unreachable and made
    // route('crm.opportunity-scores.index') ambiguous).
    Route::get('crm/opportunities/{opportunity}/signals', [OpportunityScoringController::class, 'signals'])->name('crm.opportunities.signals.index');
    Route::middleware('throttle:create_post')->group(function () {
        Route::post('crm/opportunities/{opportunity}/score', [OpportunityScoringController::class, 'scoreOpportunity'])->name('crm.opportunities.score');
        Route::post('crm/opportunities/score-all', [OpportunityScoringController::class, 'scoreAll'])->name('crm.opportunities.score-all');
        Route::post('crm/opportunities/{opportunity}/signals', [OpportunityScoringController::class, 'recordSignal'])->name('crm.opportunities.signals.store');
    });

    // AI Agents — static routes must come before {agent} param routes
    Route::get('crm/ai-agents', [AiAgentController::class, 'index']);
    Route::get('crm/ai-agents/{agent}', [AiAgentController::class, 'show']);
    Route::get('crm/ai-agents/{agent}/history', [AiAgentController::class, 'history']);
    Route::get('crm/ai-agents/{agent}/stats', [AiAgentController::class, 'stats']);
    Route::middleware('throttle:create_post')->group(function () {
        Route::post('crm/ai-agents/run-scheduled', [AiAgentController::class, 'scheduledRun']);
        Route::post('crm/ai-agents', [AiAgentController::class, 'store']);
        Route::put('crm/ai-agents/{agent}', [AiAgentController::class, 'update']);
        Route::delete('crm/ai-agents/{agent}', [AiAgentController::class, 'destroy']);
        Route::post('crm/ai-agents/{agent}/run', [AiAgentController::class, 'run']);
        Route::post('crm/ai-agents/{agent}/toggle', [AiAgentController::class, 'toggle']);
    });

    // Pipeline Analytics (complex)
    Route::middleware('throttle:complex_get')->prefix('crm/pipeline-analytics')->group(function () {
        Route::get('dashboard', [PipelineAnalyticsController::class, 'dashboard']);
        Route::get('win-rate', [PipelineAnalyticsController::class, 'winRate']);
        Route::get('conversion-funnel', [PipelineAnalyticsController::class, 'conversionFunnel']);
        Route::get('sales-velocity', [PipelineAnalyticsController::class, 'salesVelocity']);
        Route::get('stage-distribution', [PipelineAnalyticsController::class, 'stageDistribution']);
        Route::get('trend', [PipelineAnalyticsController::class, 'pipelineTrend']);
        Route::get('top-performers', [PipelineAnalyticsController::class, 'topPerformers']);
        Route::get('win-loss-reasons', [PipelineAnalyticsController::class, 'winLossReasons']);
        Route::get('avg-sales-cycle', [PipelineAnalyticsController::class, 'avgSalesCycle']);
    });
    Route::middleware('throttle:create_post')->prefix('crm/pipeline-analytics')->group(function () {
        Route::post('record-win', [PipelineAnalyticsController::class, 'recordWin']);
        Route::post('record-loss', [PipelineAnalyticsController::class, 'recordLoss']);
        Route::post('snapshots', [PipelineAnalyticsController::class, 'takeSnapshot']);
    });

    // CPQ — Quotes
    Route::get('crm/quotes', [QuoteController::class, 'index'])->name('crm.quotes.index');
    Route::get('crm/quotes/{quote}', [QuoteController::class, 'show'])->name('crm.quotes.show');
    Route::get('crm/quotes/{quote}/pdf', [QuoteController::class, 'pdf'])->name('crm.quotes.pdf');
    Route::middleware('throttle:create_post')->group(function () {
        Route::post('crm/quotes', [QuoteController::class, 'store'])->name('crm.quotes.store');
        Route::put('crm/quotes/{quote}', [QuoteController::class, 'update'])->name('crm.quotes.update');
        Route::delete('crm/quotes/{quote}', [QuoteController::class, 'destroy'])->name('crm.quotes.destroy');
        Route::post('crm/quotes/{quote}/lines', [QuoteController::class, 'addLine'])->name('crm.quotes.lines.store');
        Route::post('crm/quotes/{quote}/duplicate', [QuoteController::class, 'duplicate'])->name('crm.quotes.duplicate');
    });

    // AI Sales Forecasting
    Route::get('crm/forecasts', [ForecastController::class, 'index'])->name('crm.forecasts.index');
    Route::middleware('throttle:create_post')->group(function () {
        Route::post('crm/forecasts/generate', [ForecastController::class, 'generate'])->name('crm.forecasts.generate');
    });

    // VoIP
    Route::get('crm/voip/call-logs', [VoipController::class, 'callLogs'])->name('crm.voip.call-logs');
    Route::get('crm/voip/call-logs/{callLog}', [VoipController::class, 'showCallLog'])->name('crm.voip.call-logs.show');
    Route::middleware('throttle:create_post')->group(function () {
        Route::post('crm/voip/call', [VoipController::class, 'call'])->name('crm.voip.call');
        Route::post('crm/voip/webhook', [VoipController::class, 'webhook'])->name('crm.voip.webhook');
    });
});

// ── AI Assisted First — Contextual AI guidance ────────────────────────────
Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user'])->prefix('v1/crm')->group(function () {
    Route::post('ai/assist', [\Modules\CRM\Http\Controllers\Api\CRMAiAssistController::class, 'assist'])
        ->name('crm.ai.assist');
});

// ── Call recordings + AI summarization ──────────────────────────────────────
// Chantier 10: added module:CRM (was missing entirely) — company_id tenant scoping and
// authorize() calls now live in CallRecordingController/CallRecordingPolicy.
Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user', 'module:CRM'])->prefix('v1/crm')->group(function () {
    Route::get('calls/{callId}', [CallRecordingController::class, 'show'])
        ->name('crm.calls.show');
    Route::post('calls/{callId}/summarize', [CallRecordingController::class, 'summarize'])
        ->name('crm.calls.summarize');
    Route::get('calls/{callId}/summary', [CallRecordingController::class, 'getSummary'])
        ->name('crm.calls.summary');
});

// ── Revenue intelligence (insights, trends, anomalies) ──────────────────────
// Chantier 10: added module:CRM (was missing entirely) — company_id tenant scoping and
// authorize() calls now live in RevenueIntelligenceController/RevenueInsightPolicy.
Route::middleware(['auth:sanctum', 'session.security', 'tenancy.user', 'module:CRM'])->prefix('v1/crm/revenue-intelligence')->group(function () {
    Route::get('insights', [RevenueIntelligenceController::class, 'getInsights'])
        ->name('crm.revenue-intelligence.insights');
    Route::post('insights/generate', [RevenueIntelligenceController::class, 'generateInsight'])
        ->name('crm.revenue-intelligence.insights.generate');
    Route::get('trends', [RevenueIntelligenceController::class, 'getTrends'])
        ->name('crm.revenue-intelligence.trends');
    Route::post('trends', [RevenueIntelligenceController::class, 'recordTrend'])
        ->name('crm.revenue-intelligence.trends.record');
    Route::get('anomalies', [RevenueIntelligenceController::class, 'getAnomalies'])
        ->name('crm.revenue-intelligence.anomalies');
    Route::post('anomalies/detect', [RevenueIntelligenceController::class, 'detectAnomaly'])
        ->name('crm.revenue-intelligence.anomalies.detect');
    Route::post('anomalies/{anomaly}/resolve', [RevenueIntelligenceController::class, 'resolveAnomaly'])
        ->name('crm.revenue-intelligence.anomalies.resolve');
    Route::get('summary', [RevenueIntelligenceController::class, 'getPerformanceSummary'])
        ->name('crm.revenue-intelligence.summary');
});

// Chantier 32.15: the no-code workflow builder (crm/workflows/*, WorkflowBuilderController)
// was removed here — confirmed dead/fake at the 14-layer audit: real persistence, zero
// execution engine (activate()/deactivate() only ever flip a status string, nothing anywhere
// in the app ever creates a real WorkflowExecution row), zero Vue caller, and a confirmed
// duplicate of the real, live Modules\Workflow n8n-like engine, which already registers
// crm.contact.created/crm.opportunity.won/crm.lead.qualified triggers for real. See the
// accompanying migration's docblock for the full removal rationale.
