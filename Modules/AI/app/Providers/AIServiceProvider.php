<?php

namespace Modules\AI\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\AI\Services\AiContextualAssistantService;
use Modules\AI\Services\AutomatedInsightsService;
use Modules\AI\Services\NaturalLanguageProcessingService;
use Modules\AI\Services\PredictiveAnalyticsService;
use Modules\AI\Services\RecommendationEngineService;
use Modules\AI\Providers\RouteServiceProvider;

class AIServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->register(RouteServiceProvider::class);

        // Register AI Services
        $this->app->singleton(PredictiveAnalyticsService::class);
        $this->app->singleton(RecommendationEngineService::class);
        $this->app->singleton(NaturalLanguageProcessingService::class);
        $this->app->singleton(AutomatedInsightsService::class);

        // AI Assisted First — contextual in-app assistant
        $this->app->singleton(AiContextualAssistantService::class);
        $this->app->alias(AiContextualAssistantService::class, 'ai_assistant');

        // Service aliases
        $this->app->alias(PredictiveAnalyticsService::class, 'ai_analytics');
        $this->app->alias(RecommendationEngineService::class, 'ai_recommendations');
        $this->app->alias(NaturalLanguageProcessingService::class, 'ai_nlp');
        $this->app->alias(AutomatedInsightsService::class, 'ai_insights');
    }

    public function boot()
    {
        //
    }
}
