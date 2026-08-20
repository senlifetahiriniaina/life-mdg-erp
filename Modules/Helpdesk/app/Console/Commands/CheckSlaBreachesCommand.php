<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Console\Commands;

use Illuminate\Console\Command;
use Modules\Helpdesk\Services\SlaService;

class CheckSlaBreachesCommand extends Command
{
    protected $signature = 'helpdesk:check-sla-breaches';

    protected $description = 'Mark tickets that have exceeded their SLA due time as breached';

    public function handle(SlaService $service): int
    {
        $count = $service->checkBreaches();

        $this->info("Marked {$count} ticket(s) as SLA breached.");

        return self::SUCCESS;
    }
}
