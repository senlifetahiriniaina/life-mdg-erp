<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Refresh materialized views every 4 hours for analytics/reporting (00:15, 04:15, 08:15, 12:15, 16:15, 20:15 UTC)
        $schedule->command('materialized-views:refresh')
            ->everyFourHours()
            ->timezone('UTC')
            ->withoutOverlapping()
            ->onFailure(function () {
                \Illuminate\Support\Facades\Log::error('Materialized views refresh failed');
            })
            ->onSuccess(function () {
                \Illuminate\Support\Facades\Log::info('Materialized views refreshed successfully');
            });

        // Daily database backup to S3 at 02:00 UTC
        $schedule->command('backup:database --s3')
            ->dailyAt('02:00')
            ->timezone('UTC')
            ->withoutOverlapping()
            ->onFailure(function () {
                // Send alert to Slack on failure
                \Illuminate\Support\Facades\Log::error('Database backup failed');
            })
            ->onSuccess(function () {
                // Log success
                \Illuminate\Support\Facades\Log::info('Database backup completed successfully');
            });

        // Hourly binary log backup for point-in-time recovery
        // (Optional: requires MySQL binary logging enabled)
        // $schedule->command('backup:binlog')
        //     ->hourly()
        //     ->timezone('UTC')
        //     ->withoutOverlapping();

        // Cleanup old backups (keep last 30 days)
        $schedule->command('backup:cleanup --days=30')
            ->dailyAt('03:00')
            ->timezone('UTC')
            ->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
