<?php

declare(strict_types=1);

namespace Modules\Sales\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Modules\Sales\Console\Commands\GenerateRecurringOrdersCommand;
use Modules\Sales\Services\RecurringOrderService;
use Modules\Sales\Services\SalesService;
use Nwidart\Modules\Traits\PathNamespace;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class SalesServiceProvider extends ServiceProvider
{
    use PathNamespace;

    protected string $name = 'Sales';

    protected string $nameLower = 'sales';

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
        $this->loadMigrationsFrom(module_path($this->name, 'database/migrations'));
        // Chantier 32.16 (Sales deep 14-layer audit, layer 9 — fake/dead):
        // SalesOrderPolicy used to be registered here but had zero real
        // caller anywhere (no controller ever called authorize() against
        // it — confirmed via a repo-wide grep, its own unit test only ever
        // mocked User::can() directly, never went through the Gate). Its
        // finer-grained sales.order.{view-any,view,create,update,delete}
        // permissions are a *different* set than the flat sales.{read,
        // create,update} SalesController actually checks — activating it
        // would have silently regressed the deliberate Chantier 26 volet D
        // finance-manager grant (sales.read only, no sales.order.*, so
        // finance-manager could read orders/quotations for the monthly
        // finance review but would lose that access under the policy's own
        // verbs). Deleted rather than activated: the controller's flat
        // permission checks + forTenant() scoping already provide complete,
        // empirically-verified authorization with no gap the policy would
        // have closed.
    }

    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
        $this->app->singleton(SalesService::class);
        $this->app->singleton(RecurringOrderService::class);
    }

    protected function registerCommands(): void
    {
        $this->commands([
            GenerateRecurringOrdersCommand::class,
        ]);
    }

    /**
     * Chantier 25 (volet E) — même mécanisme déjà éprouvé pour
     * Modules\Analytics ('forecasting:nightly') et Modules\Helpdesk
     * ('helpdesk:check-sla-breaches'): hooker Schedule::class directement
     * via callAfterResolving() dans le ServiceProvider du module,
     * indépendamment du binding du Kernel racine — confirmé fonctionner
     * par `php artisan schedule:list` pour ces deux précédents.
     */
    protected function registerCommandSchedules(): void
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('sales:generate-recurring-orders')
                ->name('sales:generate-recurring-orders')
                ->dailyAt('06:00')
                ->withoutOverlapping();
        });
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
        $configPath         = module_path($this->name, $relativeConfigPath);

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

        $this->publishes([$sourcePath => $viewPath], ['views', $this->nameLower . '-module-views']);

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

    public function provides(): array
    {
        return [];
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
