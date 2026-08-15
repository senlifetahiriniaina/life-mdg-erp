<?php

declare(strict_types=1);

namespace Modules\Workflow\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Modules\Workflow\Services\FlowVersionService;
use RuntimeException;

/**
 * REST controller for flow version history and rollback.
 *
 * Routes (all under /v1/flows/{id}/versions):
 *   GET  /                        — list all versions (newest first)
 *   POST /                        — snapshot current state as new version
 *   POST /{versionId}/restore     — rollback to a specific snapshot
 */
class FlowVersionController extends Controller
{
    public function __construct(
        private readonly FlowVersionService $versionService,
    ) {}

    /**
     * GET /v1/flows/{id}/versions
     */
    public function index(int $id): JsonResponse
    {
        try {
            $versions = $this->versionService->listVersions($id);
            return response()->json(['data' => $versions]);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }
    }

    /**
     * POST /v1/flows/{id}/versions
     *
     * Body (optional):
     *   { "label": "v2 — added approval step" }
     */
    public function store(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'label' => 'nullable|string|max:255',
        ]);

        try {
            $version = $this->versionService->createVersion(
                flowId:    $id,
                label:     $request->input('label'),
                createdBy: $request->user()?->id,
            );
            return response()->json(['data' => $version], 201);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }
    }

    /**
     * POST /v1/flows/{id}/versions/{versionId}/restore
     */
    public function restore(int $id, int $versionId): JsonResponse
    {
        try {
            $newVersion = $this->versionService->rollback($id, $versionId);
            return response()->json([
                'message' => "Flow #{$id} rolled back to version #{$versionId}. New version created.",
                'data'    => $newVersion,
            ]);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
}
