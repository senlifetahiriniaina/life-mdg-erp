<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Http\Requests\UploadImportFileRequest;
use Modules\Core\Http\Resources\ImportJobResource;
use Modules\Core\Jobs\ExecuteImportJob;
use Modules\Core\Jobs\ExtractAndMapImportJob;
use Modules\Core\Models\ImportJob;
use Modules\Core\Models\ImportRow;
use Modules\Core\Services\ImportExecutorService;

/**
 * @group Core - Import
 *
 * AI-powered data import from CSV, XLSX, PDF, PNG, JPG.
 */
class ImportController extends Controller
{
    public function __construct(private readonly ImportExecutorService $executor)
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Upload a file to import.
     *
     * POST /api/v1/import/upload
     */
    public function upload(UploadImportFileRequest $request): JsonResponse
    {
        $file = $request->file('file');
        $entity = $request->input('target_entity');
        $fileType = strtolower($file->getClientOriginalExtension());
        $filename = $file->getClientOriginalName();
        $storedPath = 'imports/'.uniqid('import_', true).'_'.$filename;

        Storage::disk('local')->put($storedPath, file_get_contents($file->getRealPath()) ?: '');

        $job = ImportJob::create([
            'user_id' => $request->user()->id,
            'filename' => $filename,
            'file_path' => $storedPath,
            'file_type' => $fileType,
            'target_entity' => $entity,
            'status' => 'uploaded',
        ]);

        ExtractAndMapImportJob::dispatch($job->id);

        return response()->json(new ImportJobResource($job), 202);
    }

    /**
     * List the authenticated user's import jobs.
     *
     * GET /api/v1/import/jobs
     */
    public function index(Request $request): JsonResponse
    {
        $jobs = ImportJob::where('user_id', $request->user()->id)
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => ImportJobResource::collection($jobs->items()),
            'total' => $jobs->total(),
            'per_page' => $jobs->perPage(),
            'current_page' => $jobs->currentPage(),
        ]);
    }

    /**
     * Get a single import job's status and details.
     *
     * GET /api/v1/import/jobs/{job}
     */
    public function show(Request $request, ImportJob $job): JsonResponse
    {
        $this->authorizeJob($request, $job);

        $preview = $job->rows()
            ->orderBy('row_index')
            ->limit(5)
            ->get(['row_index', 'raw_data', 'mapped_data', 'status'])
            ->toArray();

        return response()->json([
            ...(new ImportJobResource($job))->toArray($request),
            'preview_rows' => $preview,
        ]);
    }

    /**
     * Update the column mapping for a job.
     *
     * PUT /api/v1/import/jobs/{job}/mapping
     */
    public function updateMapping(Request $request, ImportJob $job): JsonResponse
    {
        $this->authorizeJob($request, $job);

        $request->validate([
            'column_mapping' => ['required', 'array'],
        ]);

        $mapping = $request->input('column_mapping');

        // Apply mapping to all pending rows
        $pendingRows = $job->rows()->where('status', 'pending')->get();
        foreach ($pendingRows as $row) {
            /** @var ImportRow $row */
            $raw = $row->raw_data;
            $mapped = [];

            foreach ($mapping as $csvColumn => $entityField) {
                if ($entityField !== '' && $entityField !== null && isset($raw[$csvColumn])) {
                    $mapped[$entityField] = $raw[$csvColumn];
                }
            }

            $row->update(['mapped_data' => $mapped]);
        }

        $job->update([
            'column_mapping' => $mapping,
            'status' => 'mapped',
        ]);

        return response()->json(new ImportJobResource($job));
    }

    /**
     * Start the import execution.
     *
     * POST /api/v1/import/jobs/{job}/execute
     */
    public function execute(Request $request, ImportJob $job): JsonResponse
    {
        $this->authorizeJob($request, $job);

        ExecuteImportJob::dispatch($job->id);

        return response()->json(['message' => 'Import queued.', 'job' => new ImportJobResource($job)], 202);
    }

    /**
     * List rows for a job, with optional status filter.
     *
     * GET /api/v1/import/jobs/{job}/rows
     */
    public function rows(Request $request, ImportJob $job): JsonResponse
    {
        $this->authorizeJob($request, $job);

        $query = $job->rows();

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $rows = $query->orderBy('row_index')->paginate(100);

        return response()->json([
            'data' => $rows->items(),
            'total' => $rows->total(),
            'current_page' => $rows->currentPage(),
        ]);
    }

    /**
     * Rollback an import.
     *
     * POST /api/v1/import/jobs/{job}/rollback
     */
    public function rollback(Request $request, ImportJob $job): JsonResponse
    {
        $this->authorizeJob($request, $job);

        $this->executor->rollbackImport($job);

        return response()->json(['message' => 'Import rolled back.', 'job' => new ImportJobResource($job)]);
    }

    private function authorizeJob(Request $request, ImportJob $job): void
    {
        if ($job->user_id !== $request->user()->id) {
            abort(403, 'Forbidden');
        }
    }
}
