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
 *
 * Chantier 32.1: this used to write 'error_message', a column that has
 * never existed on core_import_jobs (real column: 'errors' — see
 * ImportJob's own docblock), and had no handle() method at all (see
 * ExtractAndMapImportJob's docblock for the shared BaseAsyncJob::handle()
 * finding) — both fixed here, locally, without touching the shared base
 * class.
 */
class ExecuteImportJob extends BaseAsyncJob
{
    public function __construct(public readonly int $jobId) {}

    public function handle(): void
    {
        $this->execute();
    }

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
                'errors' => $e->getMessage(),
            ]);

            Log::error('[Import] ExecuteImportJob failed', [
                'job_id' => $this->jobId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
