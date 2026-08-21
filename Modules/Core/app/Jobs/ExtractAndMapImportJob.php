<?php

declare(strict_types=1);

namespace Modules\Core\Jobs;

use Illuminate\Support\Facades\Log;
use Modules\Core\Models\ImportJob;
use Modules\Core\Models\ImportRow;
use Modules\Core\Services\AiMappingService;
use Modules\Core\Services\DataExtractionService;
use Modules\Shared\Jobs\BaseAsyncJob;

/**
 * Extract data from an import file and map to structured ImportRow records.
 *
 * Type C: ImportJob doesn't have company_id, but it's a system job scoped to the current tenant.
 * We use company_id = 0 as a system job indicator.
 *
 * Chantier 32.1 found and fixed two real bugs on this exact job:
 *
 * 1. Despite the class's own name and the controller's "AI-powered data
 *    import" docblock, this job never actually called AiMappingService —
 *    confirmed via grep that the service had zero callers anywhere in the
 *    app. The real frontend (resources/js/Pages/Import/Index.vue) already
 *    expects the resulting ai_suggestions.column_mapping to pre-fill its
 *    mapping-review step; wired in below rather than left orphaned, since
 *    the service already exactly matches what the frontend was built to
 *    consume. Separately, this job used to hand-roll CSV-only parsing
 *    inline and stub every other file_type with zero rows despite the
 *    controller's own "CSV, XLSX, PDF, PNG, JPG" docblock — Modules\Core\
 *    Services\DataExtractionService (also confirmed zero callers anywhere)
 *    already implements exactly the CSV/XLSX/PDF/PNG/JPG dispatch this job
 *    was missing; wired in for csv/xlsx (both produce a real headers/rows
 *    shape that maps cleanly onto ImportRow). pdf/png/jpg are left as an
 *    honest, narrower documented gap: DataExtractionService returns raw
 *    extracted text/base64 for those, not tabular rows, and turning
 *    unstructured text/an image into structured ImportRow data would mean
 *    building a new AI-vision-to-rows pipeline never specified anywhere —
 *    out of scope for this fix, unlike the CSV/XLSX case where the target
 *    shape already existed and just needed wiring.
 * 2. BaseAsyncJob (Modules\Shared\Jobs\BaseAsyncJob) — which this class and
 *    29 other job classes across the app extend — declares no handle()
 *    method at all, and neither did this class; confirmed empirically
 *    (php artisan tinker dispatching a real job with the app's real
 *    QUEUE_CONNECTION=sync driver) that Laravel's queue dispatcher then
 *    falls back to trying __invoke(), which doesn't exist either — a
 *    guaranteed "Call to undefined method ...::__invoke()" fatal on every
 *    real dispatch of this job, before this fix. Given handle() is missing
 *    on BaseAsyncJob itself, and 28 other job classes across Accounting and
 *    other modules outside this chantier's Modules/Core scope share the
 *    same defect, this was fixed locally on Core's own 2 jobs (this one and
 *    ExecuteImportJob) rather than by patching the shared base class — see
 *    CLAUDE.md's Chantier 32.1 entry, which flags the systemic
 *    BaseAsyncJob::handle() gap for a dedicated future chantier spanning
 *    every module that extends it.
 */
class ExtractAndMapImportJob extends BaseAsyncJob
{
    public function __construct(public readonly int $jobId) {}

    public function handle(): void
    {
        $this->execute();
    }

    private function mappingService(): AiMappingService
    {
        return app(AiMappingService::class);
    }

    private function extractionService(): DataExtractionService
    {
        return app(DataExtractionService::class);
    }

    protected function execute(): void
    {
        $importJob = ImportJob::find($this->jobId);

        if (! $importJob) {
            Log::warning('[Import] Job not found', ['job_id' => $this->jobId]);

            return;
        }

        try {
            $importJob->update(['status' => 'extracting']);

            if (in_array($importJob->file_type, ['csv', 'xlsx'], true)) {
                $this->extractTabular($importJob);
            } else {
                // pdf/png/jpg — no tabular headers/rows shape to build
                // ImportRow records from (DataExtractionService returns raw
                // text/base64 for these, not rows); mark as extracted with
                // no rows rather than guess a structure. See this class's
                // own docblock.
                $importJob->update(['status' => 'extracted', 'total_rows' => 0]);
            }
        } catch (\Throwable $e) {
            $importJob->update([
                'status' => 'failed',
                'errors' => $e->getMessage(),
            ]);

            Log::error('[Import] ExtractAndMap failed', [
                'job_id' => $this->jobId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function extractTabular(ImportJob $importJob): void
    {
        $extracted = $importJob->file_type === 'xlsx'
            ? $this->extractionService()->extractFromXlsx($importJob->file_path)
            : $this->extractionService()->extractFromCsv($importJob->file_path);

        $headers = $extracted['headers'] ?? [];
        $rows = $extracted['rows'] ?? [];

        $rowIdx = 0;
        foreach ($rows as $raw) {
            ImportRow::create([
                'import_job_id' => $importJob->id,
                'row_index' => $rowIdx,
                'raw_data' => $raw ?: [],
                'status' => 'pending',
            ]);

            $rowIdx++;
        }

        $aiSuggestions = null;
        if ($headers !== [] && $importJob->target_entity !== null) {
            try {
                $aiSuggestions = $this->mappingService()->suggestMapping($headers, $importJob->target_entity);
            } catch (\Throwable $e) {
                // AI mapping is a convenience, never a hard requirement — the
                // frontend's manual mapping step still works with none.
                Log::warning('[Import] AI mapping suggestion failed', [
                    'job_id' => $importJob->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $importJob->update([
            'status' => 'extracted',
            'total_rows' => $rowIdx,
            'ai_suggestions' => $aiSuggestions,
        ]);
    }
}
