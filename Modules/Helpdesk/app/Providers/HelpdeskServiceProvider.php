<?php

namespace Modules\Helpdesk\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Modules\Helpdesk\Console\Commands\CheckSlaBreachesCommand;
use Modules\Helpdesk\Policies\CustomerServiceAIPolicy;
use Modules\Helpdesk\Services\AI\HelpdeskAIService;
use Nwidart\Modules\Traits\PathNamespace;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class HelpdeskServiceProvider extends ServiceProvider
{
    use PathNamespace;

    protected string $name = 'Helpdesk';

    protected string $nameLower = 'helpdesk';

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
        $this->registerTicketSourceMorphMap();
        $this->registerCustomerServiceAiGates();

        $this->loadMigrationsFrom(module_path($this->name, 'database/migrations'));
    }

    /**
     * CustomerServiceAIPolicy bundles authorization for 16 different cs-ai models
     * (SentimentScore, RoutingRule, EscalationPrediction, PerformanceGoal, ...) under
     * one class, so it doesn't fit Laravel's one-policy-per-model auto-discovery or
     * the App\Providers\AppServiceProvider::$policies model=>policy map. Registering
     * each of its public ability methods as its own Gate ability lets
     * CustomerServiceAIController call $this->authorize('viewSentimentAnalysis') /
     * $this->authorize('updatePerformanceGoal', $goal) the same way every other
     * policy-backed controller in this codebase does.
     */
    private function registerCustomerServiceAiGates(): void
    {
        foreach (get_class_methods(CustomerServiceAIPolicy::class) as $ability) {
            Gate::define($ability, [CustomerServiceAIPolicy::class, $ability]);
        }
    }

    /**
     * Aliases accepted as `source_module` when raising a ticket about a
     * record from another module (POST /api/v1/helpdesk/tickets, and the
     * HelpdeskLinkable trait). Deliberately an allowlist — never resolve a
     * class name coming straight from client input.
     */
    private function registerTicketSourceMorphMap(): void
    {
        Relation::morphMap([
            'invoice' => \Modules\Accounting\Models\Invoice::class,
            'contact' => \Modules\CRM\Models\Contact::class,
            'product' => \Modules\Inventory\Models\Product::class,
            'sales_order' => \Modules\Sales\Models\SalesOrder::class,
            'purchase_order' => \Modules\Achats\Models\PurchaseOrder::class,
            'project' => \Modules\Projects\Models\Project::class,
            'shipment' => \Modules\Logistics\Models\Shipment::class,
            'employee' => \Modules\HR\Models\Employee::class,
        ]);
    }

    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->app->register(EventServiceProvider::class);
        $this->app->register(RouteServiceProvider::class);
        $this->app->singleton(HelpdeskAIService::class);
    }

    /**
     * Register commands in the format of Command::class
     *
     * CheckSlaBreachesCommand ('helpdesk:check-sla-breaches') existed,
     * fully functional (its real logic, SlaService::checkBreaches(), is
     * also correctly reachable via POST helpdesk/sla/check), but was never
     * registered here — this stub was left as a no-op comment — so the
     * command didn't exist as far as Artisan was concerned at all
     * (confirmed empirically: `php artisan helpdesk:check-sla-breaches`
     * failed with "no commands defined in the helpdesk namespace"). It also
     * physically lived at Modules/Helpdesk/Console/Commands/... while
     * declaring namespace Modules\Helpdesk\Console\Commands — composer.json
     * only maps Modules\Helpdesk\ to Modules/Helpdesk/app/, so even a
     * manual $this->commands([...]) call would have failed to autoload the
     * class; moved to Modules/Helpdesk/app/Console/Commands/ to match.
     */
    protected function registerCommands(): void
    {
        $this->commands([
            CheckSlaBreachesCommand::class,
        ]);
    }

    /**
     * Register command Schedules.
     *
     * app/Console/Kernel.php's schedule() method is never actually invoked
     * by this app — bootstrap/app.php's Application::configure() never
     * binds Illuminate\Contracts\Console\Kernel to App\Console\Kernel, so
     * the framework's own default Illuminate\Foundation\Console\Kernel
     * (an empty schedule()) is what runs (confirmed empirically: none of
     * that file's entries, including backups, ever appear in
     * `php artisan schedule:list`) — a real, severe, app-wide gap flagged
     * for a dedicated future chantier, out of this module's scope to fix.
     * The one schedule entry that *does* work anywhere in this app
     * (Modules\Analytics\Providers\AnalyticsServiceProvider's
     * 'forecasting:nightly') proves the actual working mechanism: hooking
     * Schedule::class directly via callAfterResolving() inside a module's
     * own service provider, independent of which Kernel class is bound.
     * Mirrored here so SLA-breach checking is genuinely scheduled rather
     * than only reachable via a manual `php artisan
     * helpdesk:check-sla-breaches` or POST helpdesk/sla/check call.
     */
    protected function registerCommandSchedules(): void
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('helpdesk:check-sla-breaches')
                ->name('helpdesk:check-sla-breaches')
                ->everyFifteenMinutes()
                ->withoutOverlapping();
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
