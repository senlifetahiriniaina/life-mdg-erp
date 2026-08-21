<?php

namespace Modules\AI\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\AI\Services\AiContextualAssistantService;
use Modules\AI\Providers\RouteServiceProvider;

class AIServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->register(RouteServiceProvider::class);

        // AI Assisted First — contextual in-app assistant
        $this->app->singleton(AiContextualAssistantService::class);
        $this->app->alias(AiContextualAssistantService::class, 'ai_assistant');

        // Chantier 32.2 (14-layer deep audit): PredictiveAnalyticsService,
        // RecommendationEngineService, NaturalLanguageProcessingService,
        // AutomatedInsightsService, and AnthropicCacheService were deleted —
        // confirmed via a full-repo grep (not just this module) to have zero
        // callers anywhere outside their own class and the tests that
        // instantiated them directly (`new X()`, never via this container).
        // Each was either 100% fake demo scaffolding (uniqid()/rand() fake
        // scores, `Cache::getRedis()->keys()` calls that fatal outright on
        // this app's real `file` cache driver) or a functional duplicate of
        // a real, live, already-used implementation elsewhere in the app:
        // predictive models → Modules\Analytics's real ForecastingEngineService
        // and Modules\BI's real, tested PredictiveAnalyticsService (same class
        // name, different, genuinely working namespace); anomaly detection →
        // this module's own real, routed AiAnomalyDetectionService; response
        // caching → AiContextualAssistantService's own inline Cache::remember()
        // (the exact module+action+locale+role+context-hash keying
        // AnthropicCacheService was built for, just never actually wired to
        // it). RecommendationEngineService's own domain (a generic e-commerce
        // product recommender — hardcoded 'electronics'/'software'/'services'
        // catalog) is doubly out of scope: Ecommerce isn't one of Life MDG's
        // 27 modules at all (see CLAUDE.md's Scope table). Their prior
        // presence here (bound as singletons + `ai_analytics`/
        // `ai_recommendations`/`ai_nlp`/`ai_insights` aliases, confirmed
        // unreferenced anywhere by name) was inert scaffold debt from the
        // original WideHalo extraction — deleted rather than left
        // undecided, matching this session's Chantier 9/32.1 precedent for
        // confirmed-dead code.
    }

    public function boot()
    {
        //
    }
}
