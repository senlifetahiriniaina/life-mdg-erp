<?php

declare(strict_types=1);

namespace Modules\Core\Jobs;

use Illuminate\Support\Facades\Log;
use Modules\Core\Models\ImportJob;
use Modules\Core\Services\ImportExecutorService;
use Modules\Shared\Jobs\BaseAsyncJob;

/**
 * Execute the actual import of data from ImportRows into the target entity.
 *
 * Type C: ImportJob doesn't have company_id, but it's a system job scoped to the current tenant.
 * We use company_id = 0 as a system job indicator.
 */
class ExecuteImportJob extends BaseAsyncJob
{
    public function __construct(public readonly int $jobId) {}

    protected function execute(): void
    {
        $importJob = ImportJob::find($this->jobId);

        if (! $importJob) {
            Log::warning('[Import] ExecuteImportJob: job not found', ['job_id' => $this->jobId]);

            return;
        }

        try {
            $executor = app(ImportExecutorService::class);
            $executor->executeImport($importJob);
        } catch (\Throwable $e) {
            $importJob->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            Log::error('[Import] ExecuteImportJob failed', [
                'job_id' => $this->jobId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
