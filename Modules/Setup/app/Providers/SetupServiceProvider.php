<?php

declare(strict_types=1);

namespace Modules\Setup\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Modules\Setup\Console\Commands\GenerateOnboardingFunnelSnapshotsCommand;
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

        $this->registerCommands();
        $this->registerCommandSchedules();
    }

    protected function registerCommands(): void
    {
        $this->commands([
            GenerateOnboardingFunnelSnapshotsCommand::class,
        ]);
    }

    /**
     * Chantier 32.10: same mechanism already proven for Modules\Analytics
     * ('forecasting:nightly'), Modules\Helpdesk
     * ('helpdesk:check-sla-breaches'), and Modules\Sales
     * ('sales:generate-recurring-orders') — see
     * GenerateOnboardingFunnelSnapshotsCommand's own docblock for the full
     * "activate, don't delete" investigation.
     */
    protected function registerCommandSchedules(): void
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command('setup:generate-funnel-snapshots')
                ->name('setup:generate-funnel-snapshots')
                ->dailyAt('01:00')
                ->withoutOverlapping();
        });
    }
}
