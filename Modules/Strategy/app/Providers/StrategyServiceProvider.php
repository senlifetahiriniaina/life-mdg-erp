<?php

namespace Modules\Strategy\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Modules\Strategy\Models\Ratio;
use Modules\Strategy\Models\StrategyKpi;
use Modules\Strategy\Models\StrategyObjective;
use Modules\Strategy\Models\StrategyPlan;
use Modules\Strategy\Policies\RatioPolicy;
use Modules\Strategy\Policies\StrategyKpiPolicy;
use Modules\Strategy\Policies\StrategyObjectivePolicy;
use Modules\Strategy\Policies\StrategyPlanPolicy;
use Nwidart\Modules\Traits\PathNamespace;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class StrategyServiceProvider extends ServiceProvider
{
    use PathNamespace;

    protected string $name = 'Strategy';

    protected string $nameLower = 'strategy';

    /**
     * Boot the application events.
     */
    public function boot(): void
    {
        $this->registerCommands();
        $this->registerCommandSchedules();
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();
        $this->registerPolicies();
        $this->loadMigrationsFrom(module_path($this->name, 'database/migrations'));
    }

    /**
     * Chantier 8.6: RatioPolicy/StrategyKpiPolicy/StrategyObjectivePolicy were
     * all fully and correctly written but never registered with the Gate —
     * Modules-namespaced policies don't auto-discover the way App\Policies
     * ones do (same precedent as Core/BI/HR/Payroll). This was actively
     * breaking StrategyObjectiveLinkController's 7 endpoints, all of which
     * call $this->authorize(...) against StrategyObjective: an unconditional
     * 403 for every non-super-admin user, since Gate::before only bypasses
     * for super-admin and the policy itself was invisible to the Gate.
     */
    private function registerPolicies(): void
    {
        Gate::policy(Ratio::class, RatioPolicy::class);
        Gate::policy(StrategyKpi::class, StrategyKpiPolicy::class);
        Gate::policy(StrategyObjective::class, StrategyObjectivePolicy::class);
        // Chantier 10: StrategyPlanController had full CRUD with zero Policy
        // at all (relied only on the route-level role gate) — see
        // StrategyPlanPolicy's own docblock for the full rationale.
        Gate::policy(StrategyPlan::class, StrategyPlanPolicy::class);
    }

    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
        $this->app->singleton(\Modules\Strategy\Services\StrategyPlanService::class);
        $this->app->singleton(\Modules\Strategy\Services\OkrService::class);
        $this->app->singleton(\Modules\Strategy\Services\KpiDataService::class);
        $this->app->singleton(\Modules\Strategy\Services\SignalEngineService::class);
        $this->app->singleton(\Modules\Strategy\Services\ScenarioService::class);
        $this->app->singleton(\Modules\Strategy\Services\RitualService::class);
        $this->app->singleton(\Modules\Strategy\Services\AiStrategyAdvisorService::class);
        // Strategy First — ratio & benchmark services
        $this->app->singleton(\Modules\Strategy\Services\KPIRegistryService::class);
        $this->app->singleton(\Modules\Strategy\Services\BenchmarkService::class);
        $this->app->singleton(\Modules\Strategy\Services\CorrelationAnalysisService::class);
        $this->app->singleton(\Modules\Strategy\Services\StrategyAIService::class);
        $this->app->singleton(\Modules\Strategy\Services\StrategyRatioService::class);
        $this->app->singleton(\Modules\Strategy\Services\AlignmentCascadeService::class);
    }

    /**
     * Register commands in the format of Command::class
     */
    protected function registerCommands(): void
    {
        $this->commands([
            \Modules\Strategy\Console\Commands\SnapshotRatiosCommand::class,
        ]);
    }

    /**
     * Register command Schedules.
     *
     * Chantier 32.27: was an empty stub (this module had zero scheduled
     * commands at all) — now schedules the real ratio-snapshot producer
     * daily, via the same callAfterResolving(Schedule::class, ...) pattern
     * already proven working elsewhere in this app (Analytics, Helpdesk,
     * Sales, Setup — confirmed via `php artisan schedule:list`, independent
     * of the root Kernel binding, per this session's own scheduler fix).
     */
    protected function registerCommandSchedules(): void
    {
        $this->app->booted(function () {
            $schedule = $this->app->make(\Illuminate\Console\Scheduling\Schedule::class);
            $schedule->command('strategy:snapshot-ratios')->dailyAt('02:00');
        });
    }

    /**
     * Register translations.
     */
    public function registerTranslations(): void
    {
        $langPath = resource_path('lang/modules/'.$this->nameLower);

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, $this->nameLower);
            $this->loadJsonTranslationsFrom($langPath);
        } else {
            $this->loadTranslationsFrom(module_path($this->name, 'lang'), $this->nameLower);
            $this->loadJsonTranslationsFrom(module_path($this->name, 'lang'));
        }
    }

    /**
     * Register config.
     */
    protected function registerConfig(): void
    {
        $relativeConfigPath = config('modules.paths.generator.config.path');
        $configPath = module_path($this->name, $relativeConfigPath);

        if (is_dir($configPath)) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($configPath));

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $relativePath = str_replace($configPath . DIRECTORY_SEPARATOR, '', $file->getPathname());
                    $configKey = $this->nameLower . '.' . str_replace([DIRECTORY_SEPARATOR, '.php'], ['.', ''], $relativePath);
                    $key = ($relativePath === 'config.php') ? $this->nameLower : $configKey;

                    $this->publishes([$file->getPathname() => config_path($relativePath)], 'config');
                    $this->mergeConfigFrom($file->getPathname(), $key);
                }
            }
        }
    }

    /**
     * Register views.
     */
    public function registerViews(): void
    {
        $viewPath = resource_path('views/modules/'.$this->nameLower);
        $sourcePath = module_path($this->name, 'resources/views');

        $this->publishes([$sourcePath => $viewPath], ['views', $this->nameLower.'-module-views']);

        $this->loadViewsFrom(array_merge($this->getPublishableViewPaths(), [$sourcePath]), $this->nameLower);

        $componentNamespace = $this->module_namespace($this->name, module_path($this->name, config('modules.paths.generator.component-class.path')));
        if ($componentNamespace) {
            try {
                Blade::componentNamespace($componentNamespace, $this->nameLower);
            } catch (\Exception $e) {
                // Silently skip if component namespace registration fails
            }
        }
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [];
    }

    private function getPublishableViewPaths(): array
    {
        $paths = [];
        foreach (config('view.paths') as $path) {
            if (is_dir($path.'/modules/'.$this->nameLower)) {
                $paths[] = $path.'/modules/'.$this->nameLower;
            }
        }

        return $paths;
    }
}
