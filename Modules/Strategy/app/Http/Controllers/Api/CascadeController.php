<?php

declare(strict_types=1);

namespace Modules\Strategy\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Strategy\Services\AlignmentCascadeService;

/**
 * GET /api/v1/strategy/cascade
 *
 * Returns the full OKR alignment cascade map with RAG status, linked ratios, and stats.
 */
class CascadeController extends Controller
{
    public function __construct(
        private readonly AlignmentCascadeService $cascadeService,
    ) {}

    /**
     * Return the full cascade map.
     *
     * Optional query parameters:
     *  - plan_id (int): filter objectives by plan
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $planId   = $request->query('plan_id');

        $map = $this->cascadeService->getCascadeMap($tenantId);

        // If plan_id is provided, filter root nodes to match the plan
        if ($planId !== null) {
            $planId      = (int) $planId;
            $map['nodes'] = $this->filterByPlan($map['nodes'], $planId);
            $map['stats'] = $this->recomputeStats($map['nodes']);
        }

        return response()->json([
            'data' => $map,
            'meta' => [
                'plan_id' => $planId,
            ],
        ]);
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    /**
     * Chantier 8.6 (Strategy): already used $request->user()?->tenant_id (the
     * correct, non-client-controlled column) before this pass — extracted into
     * the same private helper as the other Strategy controllers for
     * consistency. See StrategyPlanController::tenantId() for the full
     * rationale on why this column (not X-Tenant-Id) is the right source.
     */
    private function tenantId(Request $request): string
    {
        return (string) ($request->user()?->tenant_id ?? 'default');
    }

    /**
     * Recursively filter tree nodes to only include those belonging to a plan.
     *
     * @param  array<int, array<string, mixed>>  $nodes
     * @return array<int, array<string, mixed>>
     */
    private function filterByPlan(array $nodes, int $planId): array
    {
        return array_values(array_filter(
            $nodes,
            fn (array $node) => ($node['plan_id'] ?? null) === $planId,
        ));
    }

    /**
     * Re-compute stats after filtering.
     *
     * @param  array<int, array<string, mixed>>  $nodes
     * @return array{total: int, on_track: int, at_risk: int, behind: int}
     */
    private function recomputeStats(array $nodes): array
    {
        $flat = $this->flattenTree($nodes);

        $stats = [
            'total'    => count($flat),
            'on_track' => 0,
            'at_risk'  => 0,
            'behind'   => 0,
        ];

        foreach ($flat as $node) {
            match ($node['status'] ?? '') {
                'on_track' => $stats['on_track']++,
                'at_risk'  => $stats['at_risk']++,
                'behind'   => $stats['behind']++,
                default    => null,
            };
        }

        return $stats;
    }

    /**
     * Flatten a recursive node tree into a flat array.
     *
     * @param  array<int, array<string, mixed>>  $nodes
     * @return array<int, array<string, mixed>>
     */
    private function flattenTree(array $nodes): array
    {
        $flat = [];
        foreach ($nodes as $node) {
            $children        = $node['children'] ?? [];
            $node['children'] = [];
            $flat[]           = $node;
            foreach ($this->flattenTree($children) as $child) {
                $flat[] = $child;
            }
        }
        return $flat;
    }
}
