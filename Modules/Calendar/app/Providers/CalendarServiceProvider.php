<?php

declare(strict_types=1);

namespace Modules\Calendar\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Modules\Calendar\Models\Calendar;
use Modules\Calendar\Models\CalendarEvent;
use Modules\Calendar\Policies\CalendarEventPolicy;
use Modules\Calendar\Policies\CalendarPolicy;
use Modules\Calendar\Services\AppleCalendarService;
use Modules\Calendar\Services\CalendarService;
use Modules\Calendar\Services\GoogleCalendarService;
use Modules\Calendar\Services\ICalExportService;
use Modules\Calendar\Services\ModuleEventAggregatorService;
use Modules\Calendar\Services\OutlookCalendarService;
use Nwidart\Modules\Traits\PathNamespace;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class CalendarServiceProvider extends ServiceProvider
{
    use PathNamespace;

    protected string $name      = 'Calendar';
    protected string $nameLower = 'calendar';

    public function boot(): void
    {
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();
        $this->registerPolicies();
        $this->loadMigrationsFrom(module_path($this->name, 'database/migrations'));
    }

    /**
     * Chantier 8.6: CalendarPolicy/CalendarEventPolicy were both fully and
     * correctly written and already called via $this->authorize() in
     * CalendarController (4 call sites), but never registered with the
     * Gate — Modules-namespaced policies don't auto-discover the way
     * App\Policies ones do (same precedent as Core/BI/HR/Payroll/Strategy).
     * This was actively breaking every calendar/event update or delete:
     * an unconditional AuthorizationException for every user, including
     * admins, since the policy was invisible to the Gate.
     */
    private function registerPolicies(): void
    {
        Gate::policy(Calendar::class, CalendarPolicy::class);
        Gate::policy(CalendarEvent::class, CalendarEventPolicy::class);
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);

        // Bind services
        $this->app->singleton(CalendarService::class);
        $this->app->singleton(ICalExportService::class);
        $this->app->singleton(ModuleEventAggregatorService::class);

        $this->app->singleton(GoogleCalendarService::class, fn () => new GoogleCalendarService(
            clientId: config('calendar.google.client_id', ''),
            clientSecret: config('calendar.google.client_secret', ''),
            redirectUri: config('calendar.google.redirect_uri', ''),
        ));

        $this->app->singleton(OutlookCalendarService::class, fn () => new OutlookCalendarService(
            clientId: config('calendar.outlook.client_id', ''),
            clientSecret: config('calendar.outlook.client_secret', ''),
            tenantId: config('calendar.outlook.tenant_id', 'common'),
            redirectUri: config('calendar.outlook.redirect_uri', ''),
        ));

        $this->app->singleton(AppleCalendarService::class, fn () => new AppleCalendarService(
            calDavServer: config('calendar.apple.caldav_server', 'https://caldav.icloud.com'),
        ));
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
            } catch (\Exception) {
                // Silently skip if component namespace registration fails
            }
        }
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

    public function provides(): array
    {
        return [];
    }
}
