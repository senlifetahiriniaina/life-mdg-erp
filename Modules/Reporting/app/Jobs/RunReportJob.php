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
use Modules\Reporting\Services\ReportGenerationService;

/**
 * Queued job for asynchronous report execution.
 * Dispatched when a report is run via the API or on schedule.
 */
class RunReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300;

    public function __construct(
        public readonly int    $executionId,
        public readonly array  $params = [],
        public readonly string $outputFormat = 'json',
    ) {}

    public function handle(ReportGenerationService $service): void
    {
        $execution = ReportExecution::findOrFail($this->executionId);

        if ($execution->status !== 'queued') {
            Log::warning('RunReportJob: execution not in queued state', [
                'execution_id' => $this->executionId,
                'status'       => $execution->status,
            ]);
            return;
        }

        $definition = $execution->definition;

        if (! $definition instanceof ReportDefinition) {
            $execution->update([
                'status'        => 'failed',
                'error_message' => 'Report definition not found.',
                'completed_at'  => now(),
            ]);
            return;
        }

        try {
            $execution->update([
                'status'     => 'running',
                'started_at' => now(),
            ]);

            $startMs = (int) (microtime(true) * 1000);

            $completed = $service->run($definition, $this->params, $this->outputFormat, $execution);

            $duration = (int) (microtime(true) * 1000) - $startMs;

            $completed->update([
                'status'       => 'completed',
                'duration_ms'  => $duration,
                'completed_at' => now(),
            ]);

        } catch (\Throwable $e) {
            Log::error('RunReportJob failed', [
                'execution_id' => $this->executionId,
                'error'        => $e->getMessage(),
            ]);

            $execution->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at'  => now(),
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        ReportExecution::where('id', $this->executionId)->update([
            'status'        => 'failed',
            'error_message' => 'Job failed after ' . $this->tries . ' attempts: ' . $exception->getMessage(),
            'completed_at'  => now(),
        ]);
    }
}
