<?php

declare(strict_types=1);

namespace Modules\Core\Jobs;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Models\ImportJob;
use Modules\Core\Models\ImportRow;
use Modules\Shared\Jobs\BaseAsyncJob;

/**
 * Extract data from an import file and map to structured ImportRow records.
 *
 * Type C: ImportJob doesn't have company_id, but it's a system job scoped to the current tenant.
 * We use company_id = 0 as a system job indicator.
 */
class ExtractAndMapImportJob extends BaseAsyncJob
{
    public function __construct(public readonly int $jobId) {}

    protected function execute(): void
    {
        $importJob = ImportJob::find($this->jobId);

        if (! $importJob) {
            Log::warning('[Import] Job not found', ['job_id' => $this->jobId]);

            return;
        }

        try {
            $importJob->update(['status' => 'extracting']);

            $path = $importJob->file_path;
            $content = Storage::disk('local')->get($path) ?? '';

            if ($importJob->file_type === 'csv') {
                $this->extractCsv($importJob, $content);
            } else {
                // For non-CSV types (xlsx, pdf, etc.) — mark as extracted with no rows in stub
                $importJob->update(['status' => 'extracted']);
            }
        } catch (\Throwable $e) {
            $importJob->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            Log::error('[Import] ExtractAndMap failed', [
                'job_id' => $this->jobId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function extractCsv(ImportJob $importJob, string $content): void
    {
        $lines = array_filter(explode("\n", trim($content)));
        $headers = [];
        $rowIdx = 0;

        foreach ($lines as $i => $line) {
            $fields = str_getcsv($line);
            if ($i === 0) {
                $headers = $fields;

                continue;
            }

            $raw = array_combine($headers, array_pad($fields, count($headers), null));

            ImportRow::create([
                'import_job_id' => $importJob->id,
                'row_index' => $rowIdx,
                'raw_data' => $raw ?: [],
                'status' => 'pending',
            ]);

            $rowIdx++;
        }

        $importJob->update([
            'status' => 'extracted',
            'total_rows' => $rowIdx,
        ]);
    }
}
