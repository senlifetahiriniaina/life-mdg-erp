<?php

namespace Modules\BI\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Modules\BI\Models\AlertRule;
use Modules\BI\Models\CustomVisualization;
use Modules\BI\Models\DataStory;
use Modules\BI\Models\ExternalDataSource;
use Modules\BI\Models\ForecastModel;
use Modules\BI\Policies\AlertPolicy;
use Modules\BI\Policies\DataStoryPolicy;
use Modules\BI\Policies\ExternalDataPolicy;
use Modules\BI\Policies\ForecastingPolicy;
use Modules\BI\Policies\VisualizationPolicy;
use Modules\BI\Services\AI\BiAIService;
use Nwidart\Modules\Traits\PathNamespace;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class BIServiceProvider extends ServiceProvider
{
    use PathNamespace;

    protected string $name = 'BI';

    protected string $nameLower = 'bi';

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
     * Chantier 8.2 found 5 fully-written policies (AlertPolicy, DataStoryPolicy,
     * ExternalDataPolicy, ForecastingPolicy, VisualizationPolicy) whose controllers
     * already call $this->authorize() against them, but none were registered with
     * Laravel's Gate anywhere — auto-discovery doesn't apply since each policy's
     * class name doesn't match its model's name (e.g. AlertRule -> AlertPolicy, not
     * AlertRulePolicy), and app/Providers/AppServiceProvider.php's $policies map
     * never listed them either. Every authorize() call on these 5 controllers was
     * failing (no policy resolvable) until this was added.
     */
    private function registerPolicies(): void
    {
        Gate::policy(AlertRule::class, AlertPolicy::class);
        Gate::policy(DataStory::class, DataStoryPolicy::class);
        Gate::policy(ExternalDataSource::class, ExternalDataPolicy::class);
        Gate::policy(ForecastModel::class, ForecastingPolicy::class);
        Gate::policy(CustomVisualization::class, VisualizationPolicy::class);
    }

    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->app->register(EventServiceProvider::class);
        $this->app->register(RouteServiceProvider::class);
        $this->app->singleton(BiAIService::class);
    }

    /**
     * Register commands in the format of Command::class
     */
    protected function registerCommands(): void
    {
        // $this->commands([]);
    }

    /**
     * Register command Schedules.
     */
    protected function registerCommandSchedules(): void
    {
        // $this->app->booted(function () {
        //     $schedule = $this->app->make(Schedule::class);
        //     $schedule->command('inspire')->hourly();
        // });
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
                    $relativePath = str_replace($configPath.DIRECTORY_SEPARATOR, '', $file->getPathname());
                    $configKey = $this->nameLower.'.'.str_replace([DIRECTORY_SEPARATOR, '.php'], ['.', ''], $relativePath);
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
