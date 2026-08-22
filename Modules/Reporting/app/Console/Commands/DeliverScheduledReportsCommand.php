<?php

declare(strict_types=1);

namespace Modules\Reporting\Console\Commands;

use Illuminate\Console\Command;
use Modules\Reporting\Jobs\DeliverScheduledReportJob;
use Modules\Reporting\Models\ReportSchedule;

/**
 * Chantier 32.22: closes a real, previously-documented gap (CLAUDE.md's own
 * Chantier 8.5ars entry: "ReportGenerationService/its two jobs... were left
 * with their `?? 1` fallback as-is (documented, not fixed) — confirmed via
 * grep neither job is ever dispatched anywhere in the app") — real,
 * already-built, already-routed scheduled report delivery
 * (`POST reporting/schedules` → ReportSchedule, DeliverScheduledReportJob,
 * ReportGenerationService::deliverByEmail()) has never actually fired on a
 * schedule; a user could create a schedule but it would sit forever with
 * `next_run_at` never advancing and no email ever sent.
 *
 * Dispatches one DeliverScheduledReportJob per active, due ReportSchedule
 * — ReportSchedule::scopeDue() (real, already written, itself orphaned
 * until now) already expresses exactly the right condition
 * (`next_run_at IS NULL OR next_run_at <= now()`).
 */
class DeliverScheduledReportsCommand extends Command
{
    protected $signature = 'reporting:deliver-scheduled';

    protected $description = 'Dispatche la livraison des rapports planifiés dont l\'échéance est atteinte ou dépassée';

    public function handle(): int
    {
        $schedules = ReportSchedule::active()->due()->get(['id']);

        foreach ($schedules as $schedule) {
            DeliverScheduledReportJob::dispatch($schedule->id);
        }

        $this->info($schedules->count() . ' rapport(s) planifié(s) mis en file de livraison.');

        return self::SUCCESS;
    }
}
