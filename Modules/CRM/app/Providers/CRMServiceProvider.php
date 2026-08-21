<?php

namespace Modules\CRM\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Modules\CRM\Models\Account;
use Modules\CRM\Models\AiAgent;
use Modules\CRM\Models\Campaign;
use Modules\CRM\Models\CallLog;
use Modules\CRM\Models\CallRecording;
use Modules\CRM\Models\Contact;
use Modules\CRM\Models\EmailSequence;
use Modules\CRM\Models\Lead;
use Modules\CRM\Models\Opportunity;
use Modules\CRM\Models\Quote;
use Modules\CRM\Models\RevenueAnomaly;
use Modules\CRM\Models\RevenueInsight;
use Modules\CRM\Models\RevenueTrend;
use Modules\CRM\Models\Territory;
use Modules\CRM\Models\WebForm;
use Modules\CRM\Observers\AccountObserver;
use Modules\CRM\Observers\ContactObserver;
use Modules\CRM\Observers\LeadObserver;
use Modules\CRM\Observers\OpportunityObserver;
use Modules\CRM\Policies\AiAgentPolicy;
use Modules\CRM\Policies\CallLogPolicy;
use Modules\CRM\Policies\CallRecordingPolicy;
use Modules\CRM\Policies\CampaignPolicy;
use Modules\CRM\Policies\EmailSequencePolicy;
use Modules\CRM\Policies\QuotePolicy;
use Modules\CRM\Policies\RevenueInsightPolicy;
use Modules\CRM\Policies\TerritoryPolicy;
use Modules\CRM\Policies\WebFormPolicy;
use Modules\CRM\Services\AI\CrmAIService;
use Modules\CRM\Services\CpqService;
use Modules\CRM\Services\ForecastService;
use Modules\CRM\Services\LeadScoringService;
use Modules\CRM\Services\PipelineAnalyticsService;
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
        $this->registerActivitySubjectMorphMap();
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
        $this->app->singleton(PipelineAnalyticsService::class);
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
     *
     * Chantier 32.15 (14-layer deep audit): the dead Workflow/WorkflowPolicy registration was
     * removed (see the accompanying migration for the removal rationale) and 6 more policies
     * were added — CallLogPolicy/EmailSequencePolicy already existed but were never registered
     * at all (fully orphaned, not even active-breakage since nothing called authorize()
     * against them either); QuotePolicy/TerritoryPolicy/WebFormPolicy/AiAgentPolicy are new,
     * closing confirmed zero-tenant-scoping gaps found on VoipController/CallLog,
     * EmailSequenceController, QuoteController/CpqService, TerritoryController/
     * TerritoryService/TerritoryForecastService, WebFormController, and AiAgentController.
     */
    protected function registerPolicies(): void
    {
        Gate::policy(Campaign::class, CampaignPolicy::class);
        Gate::policy(RevenueInsight::class, RevenueInsightPolicy::class);
        Gate::policy(RevenueTrend::class, RevenueInsightPolicy::class);
        Gate::policy(RevenueAnomaly::class, RevenueInsightPolicy::class);
        Gate::policy(CallRecording::class, CallRecordingPolicy::class);
        Gate::policy(CallLog::class, CallLogPolicy::class);
        Gate::policy(EmailSequence::class, EmailSequencePolicy::class);
        Gate::policy(Quote::class, QuotePolicy::class);
        Gate::policy(Territory::class, TerritoryPolicy::class);
        Gate::policy(WebForm::class, WebFormPolicy::class);
        Gate::policy(AiAgent::class, AiAgentPolicy::class);
    }

    /**
     * Chantier 32.15 (CRM 14-layer audit — layer 6, security): `Activity.subject_type` is a
     * client-controlled `MorphTo` (crm_activities.subject_type/subject_id) with no allowlist
     * of any kind — ActivityController::store() accepted ANY string as subject_type, and
     * show()'s `$activity->load('subject')` would eager-load and return the full raw
     * attributes of whatever class that string resolved to (Eloquent's default MorphTo
     * behaviour treats a non-mapped string as a fully-qualified class name), a real
     * arbitrary-record information-disclosure vector for any authenticated CRM-module user.
     * Registered here as a real allowlist (never a raw class name from client input) — same
     * pattern already established by Modules\Helpdesk\Providers\HelpdeskServiceProvider's own
     * ticket-source morph map, whose 'contact' entry is intentionally identical (Laravel's
     * Relation::morphMap() merges across providers, so this is a harmless duplicate, not a
     * conflict). ActivityController::store()/update() validate subject_type against exactly
     * these 4 keys.
     */
    private function registerActivitySubjectMorphMap(): void
    {
        \Illuminate\Database\Eloquent\Relations\Relation::morphMap([
            'contact' => Contact::class,
            'account' => Account::class,
            'lead' => Lead::class,
            'opportunity' => Opportunity::class,
        ]);
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
