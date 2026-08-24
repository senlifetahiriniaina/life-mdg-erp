<?php

declare(strict_types=1);

namespace Modules\CRM\Console\Commands;

use Illuminate\Console\Command;
use Modules\CRM\Services\EmailSequenceService;

/**
 * Chantier 38.3 (CRM 14-layer re-audit): the module's own "process due email-sequence
 * enrollments" logic (EmailSequenceService::processDueEnrollments()) has real subscribers
 * (a real drip-campaign step advances via SequenceEnrollment::advance()) but was, until this
 * command, only ever reachable via an authenticated HTTP endpoint
 * (`POST crm/email-sequences/process-due`) — CRMServiceProvider::registerCommandSchedules()
 * was a literal commented-out stub, the exact same "never actually scheduled" bug class
 * already fixed for BI/Sales/Reporting/Setup/Helpdesk/Analytics elsewhere this session. In
 * practice this meant a real enrolled contact's next drip step was never sent unless some
 * authenticated user happened to manually hit the endpoint — confirmed via grep, zero other
 * caller (console command, job, or scheduled task) exists anywhere in this module.
 *
 * Deliberately called with no $companyId (processes every tenant's due enrollments in one
 * sweep) — the correct shape for a scheduled cron job, unlike the per-tenant-scoped HTTP
 * endpoint this same service method also serves.
 */
class ProcessDueEmailSequencesCommand extends Command
{
    protected $signature = 'crm:process-due-email-sequences';

    protected $description = "Traite les inscriptions de séquence d'e-mail CRM dont la prochaine étape est due";

    public function handle(EmailSequenceService $service): int
    {
        $count = $service->processDueEnrollments();

        $this->info("{$count} inscription(s) de séquence traitée(s).");

        return self::SUCCESS;
    }
}
