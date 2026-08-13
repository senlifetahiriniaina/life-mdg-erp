<?php

declare(strict_types=1);

namespace Modules\Setup\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Setup\Models\ImportJob;
use Modules\Setup\Services\ImportExecutorService;
use Throwable;

/**
 * ExecuteImportJob
 *
 * Queued background job that runs the full data import pipeline for a Setup
 * import job.  Dispatched by SetupController::executeJob() so that large
 * CSV / Excel files never time-out the HTTP request.
 *
 * Status flow managed by this job (combined with ImportExecutorService):
 *   pending/mapping/validating → analyzing (queued, before service runs)
 *   analyzing → importing         (set by ImportExecutorService::execute)
 *   importing → completed|failed  (set by ImportExecutorService::execute)
 *
 * On queue failure (unhandled exception after all retries): failed() is called
 * and sets status = 'failed' with an error_summary entry.
 */
class ExecuteImportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Maximum number of attempts before the job is considered permanently failed.
     */
    public int $tries = 1;

    /**
     * Timeout in seconds — allow up to 30 minutes for very large imports.
     */
    public int $timeout = 1800;

    public function __construct(
        public readonly ImportJob $importJob,
    ) {}

    // -----------------------------------------------------------------------
    // Queue lifecycle
    // -----------------------------------------------------------------------

    public function handle(ImportExecutorService $importExecutorService): void
    {
        // Mark the job as actively processing so the UI can show a spinner.
        // We use 'analyzing' because it is a valid enum value that sits between
        // the "ready" states (mapping/validating) and the actual row-insert
        // phase ('importing'), which ImportExecutorService sets itself.
        $this->importJob->update(['status' => 'analyzing']);

        // Delegate to the service — it manages 'importing', 'completed', and
        // 'failed' transitions as well as per-row error recording.
        $importExecutorService->execute($this->importJob);
    }

    /**
     * Called by Laravel after all retry attempts have been exhausted.
     * Ensures the job ends in a terminal 'failed' state visible to the UI.
     */
    public function failed(Throwable $e): void
    {
        // Reload to get the latest DB state in case a partial update occurred.
        $this->importJob->refresh();

        $this->importJob->update([
            'status'        => 'failed',
            'completed_at'  => now(),
            'error_summary' => [
                'message' => $e->getMessage(),
                'class'   => $e::class,
            ],
        ]);
    }
}
