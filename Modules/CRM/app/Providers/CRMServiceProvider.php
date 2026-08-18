<?php

namespace Modules\CRM\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Modules\CRM\Models\Account;
use Modules\CRM\Models\Campaign;
use Modules\CRM\Models\CallRecording;
use Modules\CRM\Models\Contact;
use Modules\CRM\Models\Lead;
use Modules\CRM\Models\Opportunity;
use Modules\CRM\Models\RevenueAnomaly;
use Modules\CRM\Models\RevenueInsight;
use Modules\CRM\Models\RevenueTrend;
use Modules\CRM\Models\Workflow;
use Modules\CRM\Observers\AccountObserver;
use Modules\CRM\Observers\ContactObserver;
use Modules\CRM\Observers\LeadObserver;
use Modules\CRM\Observers\OpportunityObserver;
use Modules\CRM\Policies\CallRecordingPolicy;
use Modules\CRM\Policies\CampaignPolicy;
use Modules\CRM\Policies\RevenueInsightPolicy;
use Modules\CRM\Policies\WorkflowPolicy;
use Modules\CRM\Services\AI\CrmAIService;
use Modules\CRM\Services\CpqService;
use Modules\CRM\Services\ForecastService;
use Modules\CRM\Services\LeadScoringService;
use Modules\CRM\Services\SequenceService;
use Modules\CRM\Services\TerritoryService;
use Nwidart\Modules\Traits\PathNamespace;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class CRMServiceProvider extends ServiceProvider
{
    use PathNamespace;

    protected string $name = 'CRM';

    protected string $nameLower = 'crm';

    /**
     * Boot the application events.
     */
    public function boot(): void
    {
        Contact::observe(ContactObserver::class);
        Account::observe(AccountObserver::class);
        Lead::observe(LeadObserver::class);
        Opportunity::observe(OpportunityObserver::class);
        $this->registerPolicies();
        $this->registerCommands();
        $this->registerCommandSchedules();
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();
        $this->loadMigrationsFrom(module_path($this->name, 'database/migrations'));
    }

    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->app->register(EventServiceProvider::class);
        $this->app->register(RouteServiceProvider::class);
        $this->app->singleton(CrmAIService::class);
        $this->app->singleton(SequenceService::class);
        $this->app->singleton(LeadScoringService::class);
        $this->app->singleton(CpqService::class);
        $this->app->singleton(ForecastService::class);
        $this->app->singleton(TerritoryService::class);
    }

    /**
     * Register policies with Laravel's Gate.
     *
     * Chantier 10 fix: Modules-namespaced policies don't auto-discover the way App\Policies
     * ones do (same pattern documented repeatedly elsewhere in this session — Core/BI/HR/
     * Payroll/Strategy/Calendar all needed this same explicit registration). CampaignPolicy/
     * WorkflowPolicy were already correctly written and already called via authorize() in
     * CampaignController/WorkflowBuilderController, but with no Gate registration at all every
     * one of those authorize() calls threw AuthorizationException for every non-super-admin
     * user (Gate::before() only short-circuits super-admin) — active breakage, not just a
     * missing-permission-seed gap.
     */
    protected function registerPolicies(): void
    {
        Gate::policy(Campaign::class, CampaignPolicy::class);
        Gate::policy(Workflow::class, WorkflowPolicy::class);
        Gate::policy(RevenueInsight::class, RevenueInsightPolicy::class);
        Gate::policy(RevenueTrend::class, RevenueInsightPolicy::class);
        Gate::policy(RevenueAnomaly::class, RevenueInsightPolicy::class);
        Gate::policy(CallRecording::class, CallRecordingPolicy::class);
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
