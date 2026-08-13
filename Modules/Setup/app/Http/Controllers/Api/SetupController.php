<?php

declare(strict_types=1);

namespace Modules\Setup\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Modules\Setup\Data\TargetSchemas;
use Modules\Setup\Models\FieldMapping;
use Modules\Setup\Models\ImportJob;
use Modules\Setup\Services\AiMappingService;
use Modules\Setup\Services\DatabaseSourceService;
use Modules\Setup\Services\FileAnalysisService;
use Modules\Setup\Jobs\ExecuteImportJob;
use Modules\Setup\Services\ImportExecutorService;
use Throwable;

/**
 * SetupController
 *
 * REST API for the WideHalo onboarding wizard.
 * All routes are protected by auth:sanctum middleware.
 *
 * Simplicity First: every endpoint returns clear, actionable error messages.
 * AI Assisted First: suggest-mappings endpoint surfaces Claude's suggestions.
 */
class SetupController extends Controller
{
    public function __construct(
        private readonly FileAnalysisService    $fileAnalysisService,
        private readonly AiMappingService       $aiMappingService,
        private readonly DatabaseSourceService  $dbSourceService,
        private readonly ImportExecutorService  $importExecutorService,
    ) {}

    // -----------------------------------------------------------------------
    // 1. POST /api/v1/setup/import-jobs
    // -----------------------------------------------------------------------

    /**
     * Create a new import job.
     * Accepts a file upload (CSV/Excel/PDF) or external DB config.
     */
    public function createJob(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name'             => 'required|string|max:100',
            'source_type'      => ['required', Rule::in(['excel', 'csv', 'pdf', 'database'])],
            'target_module'    => 'required|string|max:50',
            'target_entity'    => 'required|string|max:50',
            // File sources
            'file'             => 'required_if:source_type,excel,csv,pdf|file|max:51200',
            // DB sources
            'db_driver'        => ['required_if:source_type,database', Rule::in(['mysql', 'pgsql', 'sqlsrv', 'sqlite'])],
            'db_host'          => 'required_if:source_type,database|string|max:255',
            'db_port'          => 'nullable|integer',
            'db_database'      => 'required_if:source_type,database|string|max:255',
            'db_username'      => 'required_if:source_type,database|string|max:100',
            'db_password'      => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $tenantId = $this->tenantId($request);
        $data     = [
            'tenant_id'     => $tenantId,
            'name'          => $request->input('name'),
            'source_type'   => $request->input('source_type'),
            'target_module' => $request->input('target_module'),
            'target_entity' => $request->input('target_entity'),
            'status'        => 'pending',
            'created_by'    => $request->user()->id,
        ];

        // Handle file upload
        if ($request->hasFile('file')) {
            $disk      = config('setup.storage_disk', 'local');
            $importPath= config('setup.import_path', 'imports');
            $path      = $request->file('file')->store("{$importPath}/{$tenantId}", $disk);
            $data['source_file_path'] = $path;
        }

        // Handle DB config
        if ($request->input('source_type') === 'database') {
            $data['source_db_driver'] = $request->input('db_driver');
            $data['source_db_config'] = [
                'driver'   => $request->input('db_driver'),
                'host'     => $request->input('db_host'),
                'port'     => $request->input('db_port'),
                'database' => $request->input('db_database'),
                'username' => $request->input('db_username'),
                'password' => $request->input('db_password', ''),
            ];
        }

        $job = ImportJob::create($data);

        return response()->json([
            'data'    => $job->fresh(),
            'message' => 'Import job created. Call /analyze to begin.',
        ], 201);
    }

    // -----------------------------------------------------------------------
    // 2. GET /api/v1/setup/import-jobs
    // -----------------------------------------------------------------------

    public function listJobs(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);

        $query = ImportJob::forTenant($tenantId);

        if ($request->has('status')) {
            $query->byStatus((string) $request->input('status'));
        }
        if ($request->has('module')) {
            $query->forModule((string) $request->input('module'));
        }

        $jobs = $query->orderByDesc('created_at')->paginate(20);

        return response()->json($jobs);
    }

    // -----------------------------------------------------------------------
    // 3. GET /api/v1/setup/import-jobs/{job}
    // -----------------------------------------------------------------------

    public function showJob(Request $request, int $id): JsonResponse
    {
        $job = $this->findJobForTenant($request, $id);

        if ($job === null) {
            return response()->json(['message' => 'Import job not found.'], 404);
        }

        return response()->json([
            'data' => $job->load(['sourceSchema', 'fieldMappings']),
        ]);
    }

    // -----------------------------------------------------------------------
    // 4. POST /api/v1/setup/import-jobs/{job}/analyze
    // -----------------------------------------------------------------------

    public function analyzeJob(Request $request, int $id): JsonResponse
    {
        $job = $this->findJobForTenant($request, $id);

        if ($job === null) {
            return response()->json(['message' => 'Import job not found.'], 404);
        }

        if ($job->isRunning()) {
            return response()->json(['message' => 'Job is currently running and cannot be re-analyzed.'], 409);
        }

        $job->update(['status' => 'analyzing']);

        try {
            if ($job->source_type === 'database') {
                return response()->json(['message' => 'Database source jobs use /suggest-mappings directly.'], 422);
            }

            $schema = $this->fileAnalysisService->analyzeFile($job);
            $job->update(['status' => 'mapping']);

            return response()->json([
                'data'    => $schema,
                'message' => 'Analysis complete. Review the detected schema and call /suggest-mappings.',
            ]);
        } catch (Throwable $e) {
            $job->update(['status' => 'failed', 'error_summary' => ['message' => $e->getMessage()]]);

            return response()->json(['message' => 'Analysis failed: ' . $e->getMessage()], 500);
        }
    }

    // -----------------------------------------------------------------------
    // 5. POST /api/v1/setup/import-jobs/{job}/suggest-mappings
    // -----------------------------------------------------------------------

    public function suggestMappings(Request $request, int $id): JsonResponse
    {
        $job = $this->findJobForTenant($request, $id);

        if ($job === null) {
            return response()->json(['message' => 'Import job not found.'], 404);
        }

        $schema = $job->sourceSchema;

        if ($schema === null) {
            return response()->json(['message' => 'Run /analyze first to generate the source schema.'], 422);
        }

        $suggestions = $this->aiMappingService->suggestMappings($job, $schema);

        return response()->json([
            'data'        => $suggestions,
            'ai_used'     => !empty($suggestions),
            'ai_enabled'  => config('setup.ai_enabled', false),
            'message'     => empty($suggestions)
                ? 'AI mapping not available. Please map fields manually.'
                : count($suggestions) . ' AI suggestions generated. Review and confirm each mapping.',
        ]);
    }

    // -----------------------------------------------------------------------
    // 6. PUT /api/v1/setup/import-jobs/{job}/mappings
    // -----------------------------------------------------------------------

    public function saveMappings(Request $request, int $id): JsonResponse
    {
        $job = $this->findJobForTenant($request, $id);

        if ($job === null) {
            return response()->json(['message' => 'Import job not found.'], 404);
        }

        if (!$job->isEditable()) {
            return response()->json(['message' => 'Job is not in an editable state.'], 409);
        }

        $validator = Validator::make($request->all(), [
            'mappings'                       => 'required|array|min:1',
            'mappings.*.source_field'        => 'required|string|max:100',
            'mappings.*.target_field'        => 'required|string|max:100',
            'mappings.*.target_table'        => 'required|string|max:100',
            'mappings.*.transform_type'      => ['required', Rule::in(['direct', 'date_format', 'number_format', 'lookup', 'concat', 'split', 'custom'])],
            'mappings.*.transform_config'    => 'nullable|array',
            'mappings.*.is_required'         => 'boolean',
            'mappings.*.is_ai_suggested'     => 'boolean',
            'mappings.*.ai_confidence'       => 'nullable|numeric|min:0|max:1',
            'mappings.*.is_confirmed'        => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Delete existing mappings for this job and re-create
        $job->fieldMappings()->delete();

        $created = [];
        foreach ($request->input('mappings') as $mappingData) {
            $created[] = FieldMapping::create(array_merge($mappingData, [
                'import_job_id' => $job->id,
            ]));
        }

        // Update status if all required are confirmed
        $allConfirmed = $job->fieldMappings()
            ->where('is_required', true)
            ->where('is_confirmed', false)
            ->doesntExist();

        if ($allConfirmed) {
            $job->update(['status' => 'mapping']); // stays mapping until user triggers validate
        }

        return response()->json([
            'data'    => $created,
            'message' => count($created) . ' mappings saved.',
        ]);
    }

    // -----------------------------------------------------------------------
    // 7. POST /api/v1/setup/import-jobs/{job}/validate
    // -----------------------------------------------------------------------

    public function validateJob(Request $request, int $id): JsonResponse
    {
        $job = $this->findJobForTenant($request, $id);

        if ($job === null) {
            return response()->json(['message' => 'Import job not found.'], 404);
        }

        $job->update(['status' => 'validating']);

        // Check required field coverage
        $targetFields  = TargetSchemas::getSchema($job->target_module, $job->target_entity);
        $confirmedFields = $job->fieldMappings()
            ->where('is_confirmed', true)
            ->pluck('target_field')
            ->all();

        $validationErrors = [];
        foreach ($targetFields as $fieldDef) {
            if ($fieldDef['required'] && !in_array($fieldDef['field'], $confirmedFields, true)) {
                $validationErrors[] = [
                    'field'   => $fieldDef['field'],
                    'label'   => $fieldDef['label'],
                    'message' => "Required field '{$fieldDef['label']}' has no confirmed mapping.",
                ];
            }
        }

        if (!empty($validationErrors)) {
            $job->update(['status' => 'mapping']);

            return response()->json([
                'valid'   => false,
                'errors'  => $validationErrors,
                'message' => 'Validation failed: required fields are not mapped.',
            ], 422);
        }

        $job->update(['status' => 'mapping']); // ready to execute

        return response()->json([
            'valid'   => true,
            'message' => 'Validation passed. Call /execute to start the import.',
        ]);
    }

    // -----------------------------------------------------------------------
    // 8. POST /api/v1/setup/import-jobs/{job}/execute
    // -----------------------------------------------------------------------

    public function executeJob(Request $request, int $id): JsonResponse
    {
        $job = $this->findJobForTenant($request, $id);

        if ($job === null) {
            return response()->json(['message' => 'Import job not found.'], 404);
        }

        // Guard: only allow queuing from "ready" statuses to prevent double-dispatch.
        if (!in_array($job->status, ['pending', 'mapping', 'validating'], true)) {
            return response()->json([
                'message' => "Job cannot be executed in status '{$job->status}'. Expected: pending, mapping, or validating.",
            ], 409);
        }

        $confirmedCount = $job->fieldMappings()->where('is_confirmed', true)->count();
        if ($confirmedCount === 0) {
            return response()->json(['message' => 'No confirmed mappings found. Map and confirm fields first.'], 422);
        }

        // Dispatch to the queue — the HTTP response returns immediately (202 Accepted).
        // The actual row-by-row import runs in a background worker via ExecuteImportJob.
        ExecuteImportJob::dispatch($job);

        return response()->json([
            'status'  => 'queued',
            'job_id'  => $job->id,
            'message' => 'Import job has been queued and will run in the background. Poll GET /import-jobs/{id} for progress.',
        ], 202);
    }

    // -----------------------------------------------------------------------
    // 9. GET /api/v1/setup/import-jobs/{job}/errors
    // -----------------------------------------------------------------------

    public function listErrors(Request $request, int $id): JsonResponse
    {
        $job = $this->findJobForTenant($request, $id);

        if ($job === null) {
            return response()->json(['message' => 'Import job not found.'], 404);
        }

        $errors = $job->importErrors()
            ->orderBy('row_number')
            ->paginate(50);

        return response()->json($errors);
    }

    // -----------------------------------------------------------------------
    // 10. GET /api/v1/setup/source-schemas
    // -----------------------------------------------------------------------

    public function listTargetSchemas(): JsonResponse
    {
        $targets = TargetSchemas::availableTargets();

        $detailed = [];
        foreach ($targets as $target) {
            $detailed[] = array_merge($target, [
                'fields' => TargetSchemas::getSchema($target['module'], $target['entity']),
            ]);
        }

        return response()->json(['data' => $detailed]);
    }

    // -----------------------------------------------------------------------
    // 11. POST /api/v1/setup/test-connection
    // -----------------------------------------------------------------------

    public function testConnection(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'driver'   => ['required', Rule::in(['mysql', 'pgsql', 'sqlsrv', 'sqlite'])],
            'host'     => 'required_unless:driver,sqlite|string|max:255',
            'port'     => 'nullable|integer',
            'database' => 'required|string|max:255',
            'username' => 'required_unless:driver,sqlite|string|max:100',
            'password' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $config = $validator->validated();
            $this->dbSourceService->testConnection($config);

            // Also list tables for UX convenience
            $tables = $this->dbSourceService->listTables($config);

            return response()->json([
                'success' => true,
                'tables'  => $tables,
                'message' => 'Connection successful. ' . count($tables) . ' tables found.',
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function tenantId(Request $request): int
    {
        return (int) ($request->user()?->company_id
            ?? $request->header('X-Company-ID')
            ?? 0);
    }

    private function findJobForTenant(Request $request, int $id): ?ImportJob
    {
        return ImportJob::forTenant($this->tenantId($request))->find($id);
    }
}
