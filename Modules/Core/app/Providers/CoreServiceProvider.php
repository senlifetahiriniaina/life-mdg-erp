<?php

namespace Modules\Core\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Modules\Core\Models\CspViolation;
use Modules\Core\Models\CustomField;
use Modules\Core\Policies\CspViolationPolicy;
use Modules\Core\Policies\CustomFieldPolicy;
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
        $this->registerSecretsConfig();
        $this->registerViews();
        $this->registerPolicies();

        // Don't load migrations in testing environment - TestCase handles them
$this->loadMigrationsFrom(module_path($this->name, 'database/migrations'));
    }

    /**
     * Chantier 8.3 found CustomFieldPolicy fully written and already called
     * via $this->authorize() in CustomFieldController, but never registered
     * with Laravel's Gate — no auto-discovery for Modules-namespaced
     * policies, and no Gate::policy() call existed anywhere. Every
     * authorize() call against it was silently un-gated until this was
     * added. (The sibling ApprovalWorkflowPolicy registration that used to
     * live here was removed at Chantier 32.1 along with the confirmed-dead
     * Core Approval engine it gated — see CLAUDE.md's Chantier 32.1 entry.)
     */
    private function registerPolicies(): void
    {
        Gate::policy(CustomField::class, CustomFieldPolicy::class);
        Gate::policy(CspViolation::class, CspViolationPolicy::class);
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
        // Built via an explicit closure (not left to container auto-wiring):
        // DeepSeekProvider's constructor accepts an optional OpenAI\Contracts\ClientContract
        // for test injection, and that interface has a container binding (from
        // openai-php/laravel) whose factory throws ApiKeyIsMissing when no OpenAI
        // key is configured. Auto-wiring would eagerly resolve that binding for the
        // nullable param and crash — the closure below skips that resolution entirely.
        $this->app->singleton(
            \Modules\Core\Services\AI\DeepSeekProvider::class,
            fn () => new \Modules\Core\Services\AI\DeepSeekProvider()
        );
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

        // Bind security services as singletons.
        // Chantier 32.1: 10 sibling singleton() bindings/aliases used to live
        // here for classes that have never existed anywhere in this repo
        // (SearchableEncryption, PasswordlessService, IPWhitelistService,
        // GeolocationService, SuspiciousActivityService,
        // IntrusionDetectionService, SecurityAutomationService,
        // ComplianceFrameworkService, IncidentResponseService,
        // SecurityTestingService — confirmed via find/grep, referenced only
        // by this provider itself, zero other file anywhere) — a real
        // landmine (a fatal ClassNotFoundError the moment anything ever
        // resolved one, e.g. via app('passwordless') or a constructor type-
        // hint), inert only because nothing ever did. Removed rather than
        // built out: no docblock, no caller, no test, no page ever specified
        // what any of the 10 should actually do, and inventing 10
        // speculative security services with no driving requirement would
        // be exactly the "new business logic with no spec" anti-pattern
        // this session has repeatedly avoided elsewhere (see CLAUDE.md's
        // "36 of the 42 acc_ tables ... left as-is" precedent).
        $this->app->singleton(\Modules\Core\Services\MFAService::class);
        $this->app->singleton(\Modules\Core\Services\RiskAssessmentService::class);
        $this->app->singleton(\Modules\Core\Services\CsrfTokenService::class);

        // Alias for easy resolution ('erp.modules' avoids conflict with nwidart's 'modules')
        $this->app->alias(\Modules\Core\Services\AI\AIService::class, 'ai');
        $this->app->alias(\Modules\Core\Services\ModuleManager::class, 'erp.modules');
        $this->app->alias(RateLimitService::class, 'rate_limit');
        $this->app->alias(DDoSDetectionService::class, 'ddos');
        $this->app->alias(\Modules\Core\Services\KeyManagementService::class, 'key_management');
        $this->app->alias(\Modules\Core\Services\EncryptionService::class, 'encryption');
        $this->app->alias(\Modules\Core\Services\MFAService::class, 'mfa');
        $this->app->alias(\Modules\Core\Services\RiskAssessmentService::class, 'risk_assessment');
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
            // secrets:generate/secrets:rotate/secrets:rotate-due — live under
            // Modules\Core\Commands (not \Console\Commands like the three
            // above) and were never registered at all, so they didn't exist
            // as Artisan commands regardless of whether SecretsService itself
            // could be constructed.
            \Modules\Core\Commands\GenerateSecretCommand::class,
            \Modules\Core\Commands\RotateSecretCommand::class,
            \Modules\Core\Commands\RotateDueSecretsCommand::class,
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
     * registerConfig()'s generic per-file loop above namespaces every module
     * config file under "<module>.<file>" — so Modules/Core/config/secrets.php
     * lands at config('core.secrets'). But all 18 call sites across
     * SecretsService/SecretRotationManager/SecretAccessControl read the bare
     * config('secrets.*'), and 8 of them (audit.enabled, masking.enabled,
     * expiration.enable_expiration, security.verify_rotation/enable_rollback,
     * rotation.auto_rotate_enabled, notifications.send_to_accessors x2) pass
     * no default — they'd all read null and fail OPEN: audit logging silently
     * never writes, masked secrets come back in plaintext, no expiry is ever
     * set, rotation is never verified. Re-merging under the bare key here is
     * deliberately narrower than changing the generic loop above, which would
     * shift every other module's config keys too.
     */
    protected function registerSecretsConfig(): void
    {
        $path = module_path($this->name, 'config/secrets.php');

        if (is_file($path)) {
            $this->mergeConfigFrom($path, 'secrets');
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
