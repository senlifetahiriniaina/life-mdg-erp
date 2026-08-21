<?php

declare(strict_types=1);

namespace Modules\Integration\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Modules\Integration\Models\Integration;
use Modules\Integration\Models\IntegrationConnector;
use Modules\Integration\Policies\ExternalIntegrationPolicy;
use Modules\Integration\Policies\IntegrationConnectorPolicy;
use Modules\Integration\Services\IntegrationManager;
use Modules\Integration\Services\IntegrationService;
use Nwidart\Modules\Traits\PathNamespace;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class IntegrationServiceProvider extends ServiceProvider
{
    use PathNamespace;

    protected string $name = 'Integration';

    protected string $nameLower = 'integration';

    public function boot(): void
    {
        $this->registerCommands();
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();
        $this->loadMigrationsFrom(module_path($this->name, 'database/migrations'));
        Gate::policy(IntegrationConnector::class, IntegrationConnectorPolicy::class);
        // Chantier 32.6: Integration (IntegrationManager's mobile-money/
        // e-commerce registry) had zero Policy of any kind — Modules-
        // namespaced policies never auto-discover in this app (same
        // precedent documented throughout CLAUDE.md for every other module).
        Gate::policy(Integration::class, ExternalIntegrationPolicy::class);
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
        $this->app->singleton(IntegrationService::class);
        $this->app->alias(IntegrationService::class, 'integration');
        $this->app->singleton(IntegrationManager::class);
    }

    protected function registerCommands(): void
    {
        // $this->commands([]);
    }

    public function registerTranslations(): void
    {
        $langPath = resource_path('lang/modules/' . $this->nameLower);

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, $this->nameLower);
            $this->loadJsonTranslationsFrom($langPath);
        } else {
            $this->loadTranslationsFrom(module_path($this->name, 'lang'), $this->nameLower);
            $this->loadJsonTranslationsFrom(module_path($this->name, 'lang'));
        }
    }

    protected function registerConfig(): void
    {
        $relativeConfigPath = config('modules.paths.generator.config.path');
        $configPath = module_path($this->name, $relativeConfigPath);

        if (is_dir($configPath)) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($configPath));

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $relativePath = str_replace($configPath . DIRECTORY_SEPARATOR, '', $file->getPathname());
                    $configKey    = $this->nameLower . '.' . str_replace([DIRECTORY_SEPARATOR, '.php'], ['.', ''], $relativePath);
                    $key          = ($relativePath === 'config.php') ? $this->nameLower : $configKey;

                    $this->publishes([$file->getPathname() => config_path($relativePath)], 'config');
                    $this->mergeConfigFrom($file->getPathname(), $key);
                }
            }
        }
    }

    public function registerViews(): void
    {
        $viewPath   = resource_path('views/modules/' . $this->nameLower);
        $sourcePath = module_path($this->name, 'resources/views');

        if (is_dir($sourcePath)) {
            $this->publishes([$sourcePath => $viewPath], ['views', $this->nameLower . '-module-views']);
            $this->loadViewsFrom(array_merge($this->getPublishableViewPaths(), [$sourcePath]), $this->nameLower);
        }

        $componentNamespace = $this->module_namespace(
            $this->name,
            module_path($this->name, config('modules.paths.generator.component-class.path'))
        );

        if ($componentNamespace) {
            try {
                Blade::componentNamespace($componentNamespace, $this->nameLower);
            } catch (\Exception $e) {
                // Silently skip if component namespace registration fails
            }
        }
    }

    public function provides(): array
    {
        return [IntegrationService::class, 'integration'];
    }

    private function getPublishableViewPaths(): array
    {
        $paths = [];
        foreach (config('view.paths') as $path) {
            if (is_dir($path . '/modules/' . $this->nameLower)) {
                $paths[] = $path . '/modules/' . $this->nameLower;
            }
        }

        return $paths;
    }
}
