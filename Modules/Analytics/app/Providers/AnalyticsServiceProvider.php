<?php

namespace Modules\Analytics\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Modules\Analytics\Console\Commands\ForecastCommand;
use Modules\Analytics\Jobs\RunForecastingJob;
use Modules\Analytics\Models\ABTestRun;
use Modules\Analytics\Models\MLModel;
use Modules\Analytics\Models\PredictionModel;
use Modules\Analytics\Models\Recommendation;
use Modules\Analytics\Models\RecommendationModel;
use Modules\Analytics\Policies\ABTestRunPolicy;
use Modules\Analytics\Policies\MLModelPolicy;
use Modules\Analytics\Policies\PredictionModelPolicy;
use Modules\Analytics\Policies\RecommendationModelPolicy;
use Modules\Analytics\Policies\RecommendationPolicy;
use Modules\Analytics\Services\Forecasting\CashflowForecastService;
use Modules\Analytics\Services\Forecasting\DemandForecastService;
use Modules\Analytics\Services\Forecasting\HrForecastService;
use Modules\Analytics\Services\Forecasting\ProductionForecastService;
use Modules\Analytics\Services\ForecastingEngineService;
use Modules\Analytics\Providers\RouteServiceProvider;

class AnalyticsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);

        // Enregistrement des services de prévision
        $this->app->singleton(ForecastingEngineService::class);
        $this->app->singleton(DemandForecastService::class);
        $this->app->singleton(CashflowForecastService::class);
        $this->app->singleton(HrForecastService::class);
        $this->app->singleton(ProductionForecastService::class);
    }

    public function boot(): void
    {
        if ($this->app->environment() !== 'testing') {
            $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        }
        $this->registerPolicies();
        $this->registerCommands();
        $this->registerSchedule();
        $this->registerRecommendationMorphMap();
    }

    /**
     * Chantier 32.25 (audit 14 couches, Analytics) : voir le docblock de
     * `Recommendation::MORPH_TYPE_ALIASES` — enregistre les alias déjà
     * réellement utilisés par le frontend/les tests de ce module comme les
     * seules valeurs valides pour `recipient_type`/`recommended_type`,
     * plutôt qu'un FQCN brut venu du client. `Relation::morphMap()` fusionne
     * entre providers (précédent déjà confirmé sans conflit dans
     * `CRMServiceProvider::registerActivitySubjectMorphMap()`), donc cet
     * appel est sans risque même si d'autres modules enregistrent leurs
     * propres alias ailleurs.
     */
    protected function registerRecommendationMorphMap(): void
    {
        \Illuminate\Database\Eloquent\Relations\Relation::morphMap(Recommendation::MORPH_TYPE_ALIASES);
    }

    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([ForecastCommand::class]);
        }
    }

    protected function registerSchedule(): void
    {
        // Planification nocturne du job de prévision (toutes les nuits à 2h00)
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->call(fn () => RunForecastingJob::dispatchForAllTenants())
                     ->dailyAt('02:00')
                     ->name('forecasting:nightly')
                     ->withoutOverlapping()
                     ->onFailure(fn () => \Log::error('Forecasting nightly job failed'));
        });
    }

    protected function registerPolicies(): void
    {
        $policies = [
            PredictionModel::class => PredictionModelPolicy::class,
            RecommendationModel::class => RecommendationModelPolicy::class,
            Recommendation::class => RecommendationPolicy::class,
            MLModel::class => MLModelPolicy::class,
            ABTestRun::class => ABTestRunPolicy::class,
        ];

        foreach ($policies as $model => $policy) {
            \Gate::policy($model, $policy);
        }
    }
}
