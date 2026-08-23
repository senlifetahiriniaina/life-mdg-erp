<?php

declare(strict_types=1);

namespace Modules\Reporting\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Reporting\Models\ReportDefinition;
use Modules\Reporting\Models\ReportExecution;
use Modules\Reporting\Models\ReportSchedule;
use Modules\Reporting\Services\ReportGenerationService;

/**
 * Queued job for scheduled report delivery via email.
 *
 * Chantier 32.22: rewritten to take a real ReportSchedule id and resolve
 * everything from it, rather than a bag of loose constructor params — this
 * job (and RunReportJob, deleted alongside this fix) had zero real caller
 * anywhere in the app since ReportGenerationService::run() itself was
 * written; a matching real dispatch site (`reporting:deliver-scheduled`,
 * registered in ReportingServiceProvider) is added in this same chantier.
 *
 * Two real bugs, previously dormant only because nothing ever called this
 * class, are fixed here rather than carried forward into the newly-live
 * path:
 *  - `$definition->tenant_id ?? 1` / `$definition->created_by ?? 1` — the
 *    well-documented phantom-tenant-1 fallback already fixed repeatedly
 *    elsewhere in this app. Worse here than the usual instance of this bug:
 *    a *system* report (e.g. a seeded OHADA template) has `tenant_id =
 *    null` by design (global/shared across every tenant), so the schedule
 *    that actually knows which real tenant asked for delivery is
 *    ReportSchedule (whose own `tenant_id` is correctly populated by
 *    ReportingController::createSchedule()), never the definition itself.
 *  - `$definition->update(['last_run_at' => now()])` — `last_run_at` has
 *    never been a real column on `report_definitions` at all (confirmed via
 *    Schema::getColumnListing()); it silently no-op'd via Eloquent's
 *    mass-assignment guard. The real, already-migrated, already-`$fillable`
 *    `last_run_at`/`next_run_at` pair lives on ReportSchedule (which also
 *    already has a real `computeNextRunAt()` helper, itself orphaned until
 *    now) — updated on the schedule instead.
 */
class DeliverScheduledReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 600;

    public function __construct(
        public readonly int $scheduleId,
    ) {}

    public function handle(ReportGenerationService $service): void
    {
        $schedule = ReportSchedule::with('definition')->find($this->scheduleId);

        if (! $schedule) {
            Log::error('DeliverScheduledReportJob: schedule not found', [
                'schedule_id' => $this->scheduleId,
            ]);
            return;
        }

        $definition = $schedule->definition;

        if (! $definition instanceof ReportDefinition) {
            Log::error('DeliverScheduledReportJob: report definition not found', [
                'schedule_id'           => $this->scheduleId,
                'report_definition_id'  => $schedule->report_definition_id,
            ]);
            return;
        }

        $format = match ($definition->output_format) {
            // Chantier 32.26 fix: the seeded templates' real vocabulary is
            // 'xlsx' (not 'excel') — without this arm every xlsx-format
            // template silently fell through to the default and was
            // delivered as PDF.
            'excel', 'xlsx' => 'xlsx',
            'pdf'           => 'pdf',
            default         => 'pdf',
        };

        $execution = ReportExecution::create([
            'tenant_id'            => $schedule->tenant_id,
            'report_definition_id' => $definition->id,
            'executed_by'          => null,
            'triggered_by'         => 'schedule',
            'parameters'           => [],
            'output_format'        => $format,
            'status'               => 'running',
            'started_at'           => now(),
        ]);

        try {
            $completed = $service->run($definition, [], $format, $execution);

            $service->deliverByEmail($completed, $schedule->recipients ?? []);

            $schedule->update([
                'last_run_at' => now(),
                'next_run_at' => $schedule->computeNextRunAt(),
            ]);

            Log::info('DeliverScheduledReportJob: delivered successfully', [
                'schedule_id'  => $schedule->id,
                'report_id'    => $definition->id,
                'execution_id' => $completed->id,
                'recipients'   => $schedule->recipients,
            ]);

        } catch (\Throwable $e) {
            Log::error('DeliverScheduledReportJob failed', [
                'schedule_id' => $this->scheduleId,
                'error'       => $e->getMessage(),
            ]);

            $execution->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at'  => now(),
            ]);
        }
    }
}
