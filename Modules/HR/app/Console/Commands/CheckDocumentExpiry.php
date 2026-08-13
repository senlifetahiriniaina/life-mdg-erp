<?php

declare(strict_types=1);

namespace Modules\HR\Console\Commands;

use Illuminate\Console\Command;
use Modules\HR\Services\DocumentExpiryService;

/**
 * Daily scheduled command — check document expiry and send alerts.
 *
 * Schedule in App\Console\Kernel (or app/Console/Kernel.php):
 *   $schedule->command('hr:check-document-expiry')->dailyAt('07:00');
 */
class CheckDocumentExpiry extends Command
{
    protected $signature   = 'hr:check-document-expiry {--dry-run : Simulate without sending notifications}';
    protected $description = 'Check employee document expiry and send alerts at 60/30/7 day thresholds';

    public function __construct(private readonly DocumentExpiryService $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('[HR] Document expiry check started at ' . now()->toDateTimeString());

        // Refresh statuses first
        $this->service->refreshStatuses();
        $this->info('[HR] Statuses refreshed.');

        if ($this->option('dry-run')) {
            $expiring60 = $this->service->getExpiringDocuments(60)->count();
            $expiring30 = $this->service->getExpiringDocuments(30)->count();
            $expiring7  = $this->service->getExpiringDocuments(7)->count();
            $expired    = $this->service->getExpiredDocuments()->count();

            $this->table(
                ['Threshold', 'Count'],
                [
                    ['Expiring in ≤60 days', $expiring60],
                    ['Expiring in ≤30 days', $expiring30],
                    ['Expiring in ≤7 days',  $expiring7],
                    ['Already expired',      $expired],
                ],
            );
            $this->info('[HR] Dry-run complete. No notifications sent.');

            return self::SUCCESS;
        }

        $result = $this->service->runDailyAlerts();

        $this->info("[HR] Alerts sent: {$result['sent']} | Errors: {$result['errors']}");

        if ($result['errors'] > 0) {
            $this->warn('[HR] Some alerts failed — check logs for details.');
        }

        return self::SUCCESS;
    }
}
