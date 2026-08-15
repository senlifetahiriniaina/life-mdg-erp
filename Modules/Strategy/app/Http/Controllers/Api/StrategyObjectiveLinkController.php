<?php

namespace Modules\Strategy\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Modules\Strategy\Models\StrategyObjective;
use Modules\Strategy\Models\StrategyObjectiveLink;
use Modules\Strategy\Services\StrategyObjectiveLinkService;
use Illuminate\Support\Facades\Gate;

class StrategyObjectiveLinkController extends Controller
{
    public function __construct(private StrategyObjectiveLinkService $linkService)
    {
    }

    /**
     * GET /resource/{type}/{id}
     * Get the strategic objective hierarchy for a resource (if linked).
     */
    public function getResourceHierarchy(string $type, int $id): JsonResponse
    {
        $hierarchy = $this->linkService->getResourceHierarchy($type, $id);

        return response()->json([
            'hierarchy' => $hierarchy,
            'linked'    => !is_null($hierarchy['objective']),
        ]);
    }

    /**
     * POST /objective-links/link
     * Link a resource to a strategic objective.
     *
     * Request payload:
     * {
     *   "objective_id": 1,
     *   "linkable_type": "Accounting/Invoice",
     *   "linkable_id": 123,
     *   "contribution_value": 50000,
     *   "unit_type": "XOF"
     * }
     */
    public function link(Request $request): JsonResponse
    {
        $this->authorize('canManage', StrategyObjective::class);

        $validated = $request->validate([
            'objective_id'       => 'required|exists:strategy_objectives,id',
            'linkable_type'      => 'required|string|max:255',
            'linkable_id'        => 'required|integer',
            'contribution_value' => 'nullable|numeric',
            'unit_type'          => 'nullable|string|max:50',
        ]);

        $objective = StrategyObjective::findOrFail($validated['objective_id']);

        $link = $this->linkService->link(
            $objective,
            $validated['linkable_type'],
            $validated['linkable_id'],
            $validated['contribution_value'] ?? null,
            $validated['unit_type'] ?? null
        );

        return response()->json([
            'message' => 'Resource linked to objective successfully.',
            'link'    => $link,
        ], 201);
    }

    /**
     * DELETE /objective-links/{id}
     * Unlink a resource by link ID.
     */
    public function unlinkById(int $id): JsonResponse
    {
        $this->authorize('canManage', StrategyObjective::class);

        $link = StrategyObjectiveLink::findOrFail($id);
        $link->delete();

        return response()->json([
            'message' => 'Link removed successfully.',
        ]);
    }

    /**
     * DELETE /resource/{type}/{id}
     * Unlink a resource by type and ID.
     */
    public function unlink(string $type, int $id): JsonResponse
    {
        $this->authorize('canManage', StrategyObjective::class);

        $this->linkService->unlink($type, $id);

        return response()->json([
            'message' => 'Resource unlinked successfully.',
        ]);
    }

    /**
     * PUT /objective-links/{id}
     * Update contribution value for a link.
     *
     * Request payload:
     * {
     *   "contribution_value": 75000,
     *   "unit_type": "XOF"
     * }
     */
    public function updateContribution(int $id, Request $request): JsonResponse
    {
        $this->authorize('canManage', StrategyObjective::class);

        $validated = $request->validate([
            'contribution_value' => 'required|numeric',
            'unit_type'          => 'nullable|string|max:50',
        ]);

        $link = $this->linkService->updateContribution(
            $id,
            $validated['contribution_value'],
            $validated['unit_type'] ?? null
        );

        return response()->json([
            'message' => 'Contribution updated successfully.',
            'link'    => $link,
        ]);
    }

    /**
     * POST /objective-links/bulk-link
     * Bulk link multiple resources to an objective.
     *
     * Request payload:
     * {
     *   "objective_id": 1,
     *   "linkable_type": "Accounting/Invoice",
     *   "resource_ids": [123, 124, 125],
     *   "contribution_value": 50000
     * }
     */
    public function bulkLink(Request $request): JsonResponse
    {
        $this->authorize('canManage', StrategyObjective::class);

        $validated = $request->validate([
            'objective_id'       => 'required|exists:strategy_objectives,id',
            'linkable_type'      => 'required|string|max:255',
            'resource_ids'       => 'required|array',
            'resource_ids.*'     => 'integer',
            'contribution_value' => 'nullable|numeric',
        ]);

        $this->linkService->bulkLink(
            $validated['objective_id'],
            $validated['linkable_type'],
            $validated['resource_ids'],
            $validated['contribution_value'] ?? null
        );

        return response()->json([
            'message' => 'Resources linked to objective successfully.',
            'count'   => count($validated['resource_ids']),
        ], 201);
    }

    /**
     * GET /objective/{id}/links
     * Get all resources linked to an objective (paginated).
     */
    public function getLinkedResources(int $id, Request $request): JsonResponse
    {
        $this->authorize('view', StrategyObjective::class);

        $page = $request->query('page', 1);
        $perPage = $request->query('per_page', 20);

        $links = $this->linkService->getLinkedResources($id, $page, $perPage);

        return response()->json([
            'data'       => $links->items(),
            'pagination' => [
                'total'        => $links->total(),
                'count'        => $links->count(),
                'per_page'     => $links->perPage(),
                'current_page' => $links->currentPage(),
                'last_page'    => $links->lastPage(),
            ],
        ]);
    }

    /**
     * GET /objective/{id}/aggregated
     * Get aggregated contribution to an objective.
     */
    public function getAggregatedContribution(int $id): JsonResponse
    {
        $this->authorize('view', StrategyObjective::class);

        $aggregated = $this->linkService->getAggregatedContribution($id);

        return response()->json([
            'objective_id' => $id,
            'aggregated'   => $aggregated,
        ]);
    }
}
