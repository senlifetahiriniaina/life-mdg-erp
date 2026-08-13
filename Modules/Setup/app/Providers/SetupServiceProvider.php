<?php

declare(strict_types=1);

namespace Modules\Setup\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Modules\Setup\Models\ImportJob;
use Modules\Setup\Policies\ImportSessionPolicy;
use Modules\Setup\Services\AiMappingService;
use Modules\Setup\Services\DatabaseSourceService;
use Modules\Setup\Services\FileAnalysisService;
use Modules\Setup\Services\ImportExecutorService;
use Modules\Setup\Services\OnboardingMetricsService;

class SetupServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);

        // Merge default config
        $this->mergeConfigFrom(__DIR__ . '/../../config/setup.php', 'setup');

        // Register services as singletons
        $this->app->singleton(FileAnalysisService::class, function () {
            return new FileAnalysisService();
        });

        $this->app->singleton(DatabaseSourceService::class, function () {
            return new DatabaseSourceService();
        });

        $this->app->singleton(AiMappingService::class, function () {
            return new AiMappingService();
        });

        $this->app->singleton(ImportExecutorService::class, function ($app) {
            return new ImportExecutorService(
                $app->make(FileAnalysisService::class),
                $app->make(DatabaseSourceService::class),
            );
        });

        $this->app->singleton(OnboardingMetricsService::class, function () {
            return new OnboardingMetricsService();
        });
    }

    public function boot(): void
    {
        if ($this->app->environment() !== 'testing') {
            $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
        }
        Gate::policy(ImportJob::class, ImportSessionPolicy::class);
    }
}
