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

        // Expire overdue tenant sandbox environments daily
        $schedule->command('core:expire-sandboxes')->daily();

        // Daily compressed database backup (data + schema manifest, see
        // BackupDatabase/SchemaSnapshotService) at 02:00 UTC. --s3 forces
        // S3 regardless of config('backup.disk') — matches this schedule's
        // existing intent (daily backups always go off-box).
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

        // Cleanup old backups — retention now driven by config('backup.retention_days')
        // (BACKUP_RETENTION_DAYS), not hardcoded here.
        $schedule->command('backup:cleanup')
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
