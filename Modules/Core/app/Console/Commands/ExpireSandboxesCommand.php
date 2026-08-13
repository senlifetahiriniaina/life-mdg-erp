<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Services\SandboxService;

/**
 * ExpireSandboxesCommand — Tenant Sandbox Environments (Item #19)
 *
 * Scheduled cleanup command. Marks sandboxes past their expiry date as
 * "expired". Intended to run nightly via the Laravel scheduler:
 *
 *   $schedule->command('core:expire-sandboxes')->daily();
 */
class ExpireSandboxesCommand extends Command
{
    protected $signature = 'core:expire-sandboxes';

    protected $description = 'Expire sandboxes past their expiry date';

    public function handle(SandboxService $service): int
    {
        $count = $service->expireOverdue();

        $this->info("Expired {$count} sandbox(es).");

        return self::SUCCESS;
    }
}
