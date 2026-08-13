<?php

namespace Modules\Core\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Modules\Core\Services\DDoSDetectionService;
use Modules\Core\Services\RateLimitService;
use Nwidart\Modules\Traits\PathNamespace;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class CoreServiceProvider extends ServiceProvider
{
    use PathNamespace;

    protected string $name = 'Core';

    protected string $nameLower = 'core';

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

        // Don't load migrations in testing environment - TestCase handles them
$this->loadMigrationsFrom(module_path($this->name, 'database/migrations'));
    }

    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->app->register(EventServiceProvider::class);
        $this->app->register(RouteServiceProvider::class);

        // Bind AI services as singletons
        $this->app->singleton(\Modules\Core\Services\AI\AnthropicProvider::class);
        $this->app->singleton(\Modules\Core\Services\AI\OpenAIProvider::class);
        $this->app->singleton(\Modules\Core\Services\AI\AIService::class);
        $this->app->singleton(\Modules\Core\Services\ModuleManager::class);
        $this->app->singleton(\Modules\Core\Services\SyncService::class);
        $this->app->singleton(RateLimitService::class);
        $this->app->singleton(DDoSDetectionService::class);

        // Session security services
        $this->app->singleton(\Modules\Core\Services\SessionFingerprint::class);
        $this->app->singleton(\Modules\Core\Services\SessionSecurityService::class);
        $this->app->singleton(\Modules\Core\Services\SessionManagementDashboard::class);

        // Bind encryption services as singletons
        $this->app->singleton(\Modules\Core\Services\KeyManagementService::class);
        $this->app->singleton(\Modules\Core\Services\EncryptionService::class);
        $this->app->singleton(\Modules\Core\Services\SearchableEncryption::class);

        // Bind security services as singletons
        $this->app->singleton(\Modules\Core\Services\MFAService::class);
        $this->app->singleton(\Modules\Core\Services\PasswordlessService::class);
        $this->app->singleton(\Modules\Core\Services\RiskAssessmentService::class);
        $this->app->singleton(\Modules\Core\Services\IPWhitelistService::class);
        $this->app->singleton(\Modules\Core\Services\GeolocationService::class);
        $this->app->singleton(\Modules\Core\Services\SuspiciousActivityService::class);
        $this->app->singleton(\Modules\Core\Services\IntrusionDetectionService::class);
        $this->app->singleton(\Modules\Core\Services\SecurityAutomationService::class);
        $this->app->singleton(\Modules\Core\Services\ComplianceFrameworkService::class);
        $this->app->singleton(\Modules\Core\Services\IncidentResponseService::class);
        $this->app->singleton(\Modules\Core\Services\SecurityTestingService::class);
        $this->app->singleton(\Modules\Core\Services\CsrfTokenService::class);

        // Alias for easy resolution ('erp.modules' avoids conflict with nwidart's 'modules')
        $this->app->alias(\Modules\Core\Services\AI\AIService::class, 'ai');
        $this->app->alias(\Modules\Core\Services\ModuleManager::class, 'erp.modules');
        $this->app->alias(RateLimitService::class, 'rate_limit');
        $this->app->alias(DDoSDetectionService::class, 'ddos');
        $this->app->alias(\Modules\Core\Services\KeyManagementService::class, 'key_management');
        $this->app->alias(\Modules\Core\Services\EncryptionService::class, 'encryption');
        $this->app->alias(\Modules\Core\Services\SearchableEncryption::class, 'searchable_encryption');
        $this->app->alias(\Modules\Core\Services\MFAService::class, 'mfa');
        $this->app->alias(\Modules\Core\Services\PasswordlessService::class, 'passwordless');
        $this->app->alias(\Modules\Core\Services\RiskAssessmentService::class, 'risk_assessment');
        $this->app->alias(\Modules\Core\Services\IPWhitelistService::class, 'ip_whitelist');
        $this->app->alias(\Modules\Core\Services\GeolocationService::class, 'geolocation');
        $this->app->alias(\Modules\Core\Services\SuspiciousActivityService::class, 'suspicious_activity');
        $this->app->alias(\Modules\Core\Services\IntrusionDetectionService::class, 'intrusion_detection');
        $this->app->alias(\Modules\Core\Services\SecurityAutomationService::class, 'security_automation');
        $this->app->alias(\Modules\Core\Services\ComplianceFrameworkService::class, 'compliance');
        $this->app->alias(\Modules\Core\Services\IncidentResponseService::class, 'incident_response');
        $this->app->alias(\Modules\Core\Services\SecurityTestingService::class, 'security_testing');
        $this->app->alias(\Modules\Core\Services\CsrfTokenService::class, 'csrf');
        $this->app->alias(\Modules\Core\Services\SessionFingerprint::class, 'session_fingerprint');
        $this->app->alias(\Modules\Core\Services\SessionSecurityService::class, 'session_security');
        $this->app->alias(\Modules\Core\Services\SessionManagementDashboard::class, 'session_dashboard');
    }

    /**
     * Register commands in the format of Command::class
     */
    protected function registerCommands(): void
    {
        $this->commands([
            \Modules\Core\Console\Commands\AnonymizeUserCommand::class,
            \Modules\Core\Console\Commands\CspAnalyzeCommand::class,
            \Modules\Core\Console\Commands\ExpireSandboxesCommand::class,
        ]);
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
