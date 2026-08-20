<?php

namespace Modules\Strategy\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Modules\Strategy\Models\StrategyObjective;
use Modules\Strategy\Models\StrategyObjectiveLink;
use Modules\Strategy\Services\StrategyObjectiveLinkService;
use Illuminate\Support\Facades\Gate;

/**
 * Chantier 19 (Lot 5): confirmed empirically (Chantier19InvestigationTest)
 * that every mutating/read endpoint on this controller had zero tenant
 * ownership check — `authorize('canManage'/'view', StrategyObjective::class)`
 * is a class-level Gate call (StrategyObjectivePolicy::canManage() only
 * checks the caller's role), never an instance-level ownership check. Any
 * user of any company could link/unlink/read/aggregate contributions on
 * another company's real strategic objective just by knowing or guessing
 * its id. Fixed with an explicit tenant-ownership check (via the objective's
 * plan.tenant_id, the same StrategyObjective::scopeForTenant() OkrController
 * now uses) at every entry point that resolves an objective_id or link id.
 */
class StrategyObjectiveLinkController extends Controller
{
    public function __construct(private StrategyObjectiveLinkService $linkService)
    {
    }

    /**
     * GET /resource/{type}/{id}
     * Get the strategic objective hierarchy for a resource (if linked).
     */
    public function getResourceHierarchy(Request $request, string $type, int $id): JsonResponse
    {
        $hierarchy = $this->linkService->getResourceHierarchy($type, $id, $this->tenantId($request));

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

        $objective = $this->objectiveInTenant($validated['objective_id'], $this->tenantId($request));

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
    public function unlinkById(Request $request, int $id): JsonResponse
    {
        $this->authorize('canManage', StrategyObjective::class);

        $link = StrategyObjectiveLink::with('objective.plan')->findOrFail($id);
        $this->assertLinkInTenant($link, $this->tenantId($request));
        $link->delete();

        return response()->json([
            'message' => 'Link removed successfully.',
        ]);
    }

    /**
     * DELETE /resource/{type}/{id}
     * Unlink a resource by type and ID.
     */
    public function unlink(Request $request, string $type, int $id): JsonResponse
    {
        $this->authorize('canManage', StrategyObjective::class);

        // Chantier 19 (Lot 5): only ever delete links whose objective is the
        // caller's own — previously this deleted every link matching
        // type+id regardless of which company's objective it belonged to.
        $this->linkService->unlink($type, $id, $this->tenantId($request));

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

        $existing = StrategyObjectiveLink::with('objective.plan')->findOrFail($id);
        $this->assertLinkInTenant($existing, $this->tenantId($request));

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

        $this->objectiveInTenant($validated['objective_id'], $this->tenantId($request));

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
        $this->objectiveInTenant($id, $this->tenantId($request));

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
    public function getAggregatedContribution(Request $request, int $id): JsonResponse
    {
        $this->authorize('view', StrategyObjective::class);
        $this->objectiveInTenant($id, $this->tenantId($request));

        $aggregated = $this->linkService->getAggregatedContribution($id);

        return response()->json([
            'objective_id' => $id,
            'aggregated'   => $aggregated,
        ]);
    }

    private function tenantId(Request $request): string
    {
        return (string) ($request->user()?->company_id ?? 0);
    }

    /**
     * Load an objective and 404 unless it belongs to the caller's own
     * tenant (via its plan) — mirrors OkrController::objectiveInTenant().
     */
    private function objectiveInTenant(int $objectiveId, string $tenantId): StrategyObjective
    {
        $objective = StrategyObjective::with('plan')->findOrFail($objectiveId);

        abort_if((string) ($objective->plan?->tenant_id ?? '') !== $tenantId, 404);

        return $objective;
    }

    private function assertLinkInTenant(StrategyObjectiveLink $link, string $tenantId): void
    {
        abort_if(
            (string) ($link->objective?->plan?->tenant_id ?? '') !== $tenantId,
            404
        );
    }
}
