<?php

declare(strict_types=1);

namespace Modules\Settings\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Settings\Models\Setting;
use Modules\Settings\Policies\SettingPolicy;
use Modules\Settings\Services\SettingsService;

class SettingsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
        $this->app->singleton(SettingsService::class, function () {
            return new SettingsService();
        });

        // Bind a short alias so callers can resolve via the container
        $this->app->alias(SettingsService::class, 'settings');
    }

    public function boot(): void
    {
        $this->registerPolicies();
        if ($this->app->environment() !== 'testing') {
            $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
        }
    }

    private function registerPolicies(): void
    {
        $gate = $this->app->make('Illuminate\Contracts\Auth\Access\Gate');
        $gate->policy(Setting::class, SettingPolicy::class);
    }
}
