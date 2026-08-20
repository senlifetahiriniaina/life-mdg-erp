<?php

declare(strict_types=1);

namespace Modules\Setup\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Modules\Setup\Services\AiDataImportService;
use Throwable;

/**
 * DataImportController
 *
 * REST API for the AI-assisted data import pipeline (Phase 40).
 *
 * Routes:
 *   POST /api/v1/setup/import/analyze        — upload file + get AI mapping suggestions
 *   POST /api/v1/setup/import/validate       — validate mapping + data quality report
 *   POST /api/v1/setup/import/execute        — start async import job
 *   GET  /api/v1/setup/import/status/{jobId} — poll progress
 *   GET  /api/v1/setup/import/templates      — list importable entity types
 *
 * All routes require auth:sanctum.
 * Africa First: XOF, OHADA, mobile-money entities supported.
 * AI Assisted First: Claude Opus for mapping, Sonnet for validation.
 * Graceful fallback: heuristic mapping when ANTHROPIC_API_KEY is absent.
 */
class DataImportController extends Controller
{
    public function __construct(
        private readonly AiDataImportService $importService,
    ) {}

    // -------------------------------------------------------------------------
    // POST /api/v1/setup/import/analyze
    // -------------------------------------------------------------------------

    /**
     * Upload a file and receive AI-suggested column mappings.
     *
     * Accepted formats: .xlsx, .xls, .csv, .pdf (max 50 MB)
     */
    public function analyze(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file'      => 'required|file|mimes:xlsx,xls,csv,pdf,txt|max:51200',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $file     = $request->file('file');
            $tenantId = $this->resolveTenantId($request);

            // Store file temporarily (TTL handled by storage cleanup job)
            $storedPath = $file->store("imports/{$tenantId}", 'local');
            $absolutePath = Storage::disk('local')->path($storedPath);

            $result = $this->importService->analyzeFile($absolutePath, $tenantId);

            // Include stored path so subsequent calls can reference the same file
            $result['file_path'] = $storedPath;

            return response()->json($result);
        } catch (Throwable $e) {
            return response()->json([
                'error'   => 'Erreur lors de l\'analyse du fichier.',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    // -------------------------------------------------------------------------
    // POST /api/v1/setup/import/validate
    // -------------------------------------------------------------------------

    /**
     * Validate a column mapping and receive a data-quality report.
     */
    public function validate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file_path' => 'required|string',
            'mapping'   => 'required|array|min:1',
            'mapping.*.source' => 'required|string',
            'mapping.*.target' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $storedPath   = $request->input('file_path');
            $absolutePath = Storage::disk('local')->path($storedPath);
            $mapping      = $request->input('mapping');

            if (! Storage::disk('local')->exists($storedPath)) {
                return response()->json(['error' => 'Fichier introuvable. Veuillez le re-uploader.'], 404);
            }

            $result = $this->importService->validateMapping($absolutePath, $mapping);

            return response()->json($result);
        } catch (Throwable $e) {
            return response()->json([
                'error'   => 'Erreur lors de la validation du mapping.',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    // -------------------------------------------------------------------------
    // POST /api/v1/setup/import/execute
    // -------------------------------------------------------------------------

    /**
     * Start an asynchronous import job.
     * Returns a job_id that can be polled via /status/{jobId}.
     */
    public function execute(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file_path' => 'required|string',
            'mapping'   => 'required|array|min:1',
            'mapping.*.source' => 'required|string',
            'mapping.*.target' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $storedPath   = $request->input('file_path');
            $absolutePath = Storage::disk('local')->path($storedPath);
            $mapping      = $request->input('mapping');
            $tenantId     = $this->resolveTenantId($request);

            if (! Storage::disk('local')->exists($storedPath)) {
                return response()->json(['error' => 'Fichier introuvable. Veuillez le re-uploader.'], 404);
            }

            $jobId = $this->importService->executeImport($absolutePath, $mapping, $tenantId);

            return response()->json([
                'job_id'  => $jobId,
                'message' => 'Import démarré. Utilisez /status/{job_id} pour suivre la progression.',
            ], 202);
        } catch (Throwable $e) {
            return response()->json([
                'error'   => 'Erreur lors du démarrage de l\'import.',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/setup/import/status/{jobId}
    // -------------------------------------------------------------------------

    /**
     * Poll the progress of an import job.
     */
    public function status(string $jobId): JsonResponse
    {
        if (! preg_match('/^[0-9a-f\-]{36}$/i', $jobId)) {
            return response()->json(['error' => 'job_id invalide.'], 422);
        }

        $status = $this->importService->getImportStatus($jobId);

        if ($status['status'] === 'not_found') {
            return response()->json(['error' => 'Job introuvable.'], 404);
        }

        return response()->json($status);
    }

    // -------------------------------------------------------------------------
    // GET /api/v1/setup/import/templates
    // -------------------------------------------------------------------------

    /**
     * Return the list of supported entity types with their field schemas.
     */
    public function templates(): JsonResponse
    {
        return response()->json([
            'templates' => $this->importService->getTemplates(),
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Chantier 19 Lot 3: was `$request->input('tenant_id') ?: ($user->tenant_id
     * ?? 'default')` — two compounding bugs. First, a fully client-controlled
     * `tenant_id` request field was trusted outright (any authenticated user
     * could set `tenant_id` to a victim tenant's id and analyze/import into
     * their bucket) — the same client-controlled-override IDOR pattern already
     * fixed for Projects' `ProjectAdvancedController::store()` (Chantier 10)
     * and IntegrationController (Chantier 8.5-light). Second, the fallback read
     * `users.tenant_id`, the well-documented phantom column (real DB column,
     * never in `User::$fillable`, never populated by any real registration
     * path — see SetupController::tenantId()'s docblock in this same module
     * for the full investigation), collapsing every tenant that hit the
     * fallback into one shared `'default'` bucket. Both closed: the tenant is
     * now derived solely from the authenticated user's real `company_id`,
     * matching every other tenant-scoping helper in this module.
     */
    private function resolveTenantId(Request $request): string
    {
        return (string) ($request->user()?->company_id ?? 0);
    }
}
