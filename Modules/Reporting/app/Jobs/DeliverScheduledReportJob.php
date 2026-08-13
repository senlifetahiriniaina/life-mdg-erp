<?php

declare(strict_types=1);

namespace Modules\Reporting\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Modules\Reporting\Models\ReportDefinition;
use Modules\Reporting\Models\ReportExecution;
use Modules\Reporting\Services\ReportGenerationService;

/**
 * Queued job for scheduled report delivery via email.
 * Triggered by the scheduler (Kernel / Schedule) based on report_definitions.schedule JSON.
 */
class DeliverScheduledReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 600;

    /**
     * @param int    $reportDefinitionId  Report to run and deliver
     * @param array  $recipients          Email addresses
     * @param string $outputFormat        pdf|xlsx|csv
     * @param array  $params              Optional parameters (period, currency, etc.)
     */
    public function __construct(
        public readonly int    $reportDefinitionId,
        public readonly array  $recipients = [],
        public readonly string $outputFormat = 'pdf',
        public readonly array  $params = [],
    ) {}

    public function handle(ReportGenerationService $service): void
    {
        $definition = ReportDefinition::find($this->reportDefinitionId);

        if (! $definition) {
            Log::error('DeliverScheduledReportJob: report definition not found', [
                'report_definition_id' => $this->reportDefinitionId,
            ]);
            return;
        }

        // Create an execution record for this scheduled run
        $execution = ReportExecution::create([
            'tenant_id'            => $definition->tenant_id ?? 1,
            'report_definition_id' => $definition->id,
            'executed_by'          => $definition->created_by ?? 1,
            'triggered_by'         => 'schedule',
            'parameters'           => $this->params,
            'output_format'        => $this->outputFormat,
            'status'               => 'running',
            'started_at'           => now(),
        ]);

        try {
            $startMs   = (int) (microtime(true) * 1000);
            $completed = $service->run($definition, $this->params, $this->outputFormat, $execution);
            $duration  = (int) (microtime(true) * 1000) - $startMs;

            $completed->update([
                'status'       => 'completed',
                'duration_ms'  => $duration,
                'completed_at' => now(),
            ]);

            // Deliver via email
            $service->deliverByEmail($completed, $this->recipients);

            // Update last_run_at on the definition
            $definition->update(['last_run_at' => now()]);

            Log::info('DeliverScheduledReportJob: delivered successfully', [
                'report_id'    => $definition->id,
                'execution_id' => $completed->id,
                'recipients'   => $this->recipients,
            ]);

        } catch (\Throwable $e) {
            Log::error('DeliverScheduledReportJob failed', [
                'report_definition_id' => $this->reportDefinitionId,
                'error'                => $e->getMessage(),
            ]);

            $execution->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at'  => now(),
            ]);
        }
    }
}
