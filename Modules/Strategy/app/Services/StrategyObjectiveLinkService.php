<?php

namespace Modules\Strategy\Services;

use Modules\Strategy\Models\StrategyObjective;
use Modules\Strategy\Models\StrategyObjectiveLink;
use Modules\Strategy\Models\StrategyPillar;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\Paginator;

class StrategyObjectiveLinkService
{
    /**
     * Link a resource to a strategic objective.
     *
     * @param StrategyObjective $objective
     * @param string $linkableType  Format: 'Module/Model' e.g. 'Accounting/Invoice'
     * @param int $linkableId       Resource ID
     * @param float|null $value     Contribution value
     * @param string|null $unitType Unit (e.g., 'XOF', '#', 'days')
     * @return StrategyObjectiveLink
     */
    public function link(
        StrategyObjective $objective,
        string $linkableType,
        int $linkableId,
        float $value = null,
        string $unitType = null
    ): StrategyObjectiveLink {
        return StrategyObjectiveLink::create([
            'strategy_objective_id' => $objective->id,
            'linkable_type'         => $linkableType,
            'linkable_id'           => $linkableId,
            'contribution_value'    => $value,
            'unit_type'             => $unitType,
        ]);
    }

    /**
     * Unlink a resource from any strategic objective.
     *
     * @param string $linkableType
     * @param int $linkableId
     * @return void
     */
    /**
     * @param string|null $tenantId Chantier 19 (Lot 5): when given, only
     *   deletes links whose objective belongs to this tenant (via its
     *   plan.tenant_id) — previously this deleted every link matching
     *   linkable_type+linkable_id regardless of which company's objective
     *   it belonged to, a real cross-tenant IDOR since resource ids are not
     *   guaranteed unique across companies.
     */
    public function unlink(string $linkableType, int $linkableId, ?string $tenantId = null): void
    {
        $query = StrategyObjectiveLink::where('linkable_type', $linkableType)
            ->where('linkable_id', $linkableId);

        if ($tenantId !== null) {
            $query->whereHas('objective.plan', function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId);
            });
        }

        $query->delete();
    }

    /**
     * Get the linked objective for a resource (if any).
     *
     * @param string $linkableType
     * @param int $linkableId
     * @return StrategyObjective|null
     */
    public function getObjectiveForResource(string $linkableType, int $linkableId, ?string $tenantId = null): ?StrategyObjective
    {
        $link = StrategyObjectiveLink::where('linkable_type', $linkableType)
            ->where('linkable_id', $linkableId)
            ->with('objective.plan')
            ->first();

        $objective = $link ? $link->objective : null;

        // Chantier 19 (Lot 5): confirmed empirically that a resource linked
        // to another company's objective leaked that objective's title/plan/
        // pillar name to any caller who guessed the resource's type+id —
        // treat a link belonging to a different tenant as "not linked" at
        // all, the same safe shape this method already returns for a
        // genuinely-unlinked resource.
        if ($tenantId !== null && $objective && (string) ($objective->plan?->tenant_id ?? '') !== $tenantId) {
            return null;
        }

        return $objective;
    }

    /**
     * Get the full hierarchy for display: vision → pillar → objective + progress.
     *
     * @param string $linkableType
     * @param int $linkableId
     * @param string|null $tenantId
     * @return array { vision, pillar, objective, progress, health_score }
     */
    public function getResourceHierarchy(string $linkableType, int $linkableId, ?string $tenantId = null): array
    {
        $objective = $this->getObjectiveForResource($linkableType, $linkableId, $tenantId);

        if (!$objective) {
            return [
                'vision'       => null,
                'pillar'       => null,
                'objective'    => null,
                'progress'     => 0,
                'health_score' => 0,
            ];
        }

        $plan = $objective->plan;
        $pillar = $objective->pillar;

        return [
            'vision'       => $plan ? ['id' => $plan->id, 'name' => $plan->name, 'vision' => $plan->vision] : null,
            'pillar'       => $pillar ? ['id' => $pillar->id, 'name' => $pillar->name, 'color' => $pillar->color] : null,
            'objective'    => [
                'id'          => $objective->id,
                'title'       => $objective->title,
                'description' => $objective->description,
                'level'       => $objective->level,
                'status'      => $objective->status,
                'progress'    => $objective->progress,
            ],
            'progress'     => $objective->progress,
            'health_score' => $plan ? $plan->health_score : 0,
        ];
    }

    /**
     * Get all resources linked to an objective (paginated).
     *
     * @param int $objectiveId
     * @param int $page
     * @param int $perPage
     * @return mixed
     */
    public function getLinkedResources(int $objectiveId, int $page = 1, int $perPage = 20)
    {
        return StrategyObjectiveLink::where('strategy_objective_id', $objectiveId)
            ->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Bulk link resources to an objective.
     *
     * @param int $objectiveId
     * @param string $linkableType
     * @param array $resourceIds
     * @param float|null $value
     * @return void
     */
    public function bulkLink(int $objectiveId, string $linkableType, array $resourceIds, float $value = null): void
    {
        $objective = StrategyObjective::findOrFail($objectiveId);
        $links = [];

        foreach ($resourceIds as $resourceId) {
            $links[] = [
                'strategy_objective_id' => $objectiveId,
                'linkable_type'         => $linkableType,
                'linkable_id'           => $resourceId,
                'contribution_value'    => $value,
                'created_at'            => now(),
                'updated_at'            => now(),
            ];
        }

        if (!empty($links)) {
            StrategyObjectiveLink::upsert(
                $links,
                ['strategy_objective_id', 'linkable_type', 'linkable_id'],
                ['contribution_value', 'updated_at']
            );
        }
    }

    /**
     * Update contribution value for a link.
     *
     * @param int $linkId
     * @param float $value
     * @param string|null $unitType
     * @return StrategyObjectiveLink
     */
    public function updateContribution(int $linkId, float $value, string $unitType = null): StrategyObjectiveLink
    {
        $link = StrategyObjectiveLink::findOrFail($linkId);
        $link->update([
            'contribution_value' => $value,
            'unit_type'          => $unitType,
        ]);
        return $link;
    }

    /**
     * Get aggregated contribution to an objective (sum of all linked resources).
     *
     * @param int $objectiveId
     * @return array { total_value, resource_count, last_updated, by_unit_type }
     */
    public function getAggregatedContribution(int $objectiveId): array
    {
        $links = StrategyObjectiveLink::where('strategy_objective_id', $objectiveId)
            ->whereNotNull('contribution_value')
            ->get();

        $totalValue = $links->sum('contribution_value');
        $resourceCount = StrategyObjectiveLink::where('strategy_objective_id', $objectiveId)->count();
        $lastUpdated = $links->max('updated_at');

        // Group by unit type for multi-currency or mixed-unit scenarios
        $byUnitType = [];
        foreach ($links->groupBy('unit_type') as $unitType => $unitLinks) {
            $byUnitType[$unitType ?? 'unknown'] = [
                'value' => $unitLinks->sum('contribution_value'),
                'count' => $unitLinks->count(),
            ];
        }

        return [
            'total_value'    => round($totalValue, 2),
            'resource_count' => $resourceCount,
            'last_updated'   => $lastUpdated,
            'by_unit_type'   => $byUnitType,
        ];
    }

    /**
     * Check if a resource is linked to any objective.
     *
     * @param string $linkableType
     * @param int $linkableId
     * @return bool
     */
    public function isLinked(string $linkableType, int $linkableId): bool
    {
        return StrategyObjectiveLink::where('linkable_type', $linkableType)
            ->where('linkable_id', $linkableId)
            ->exists();
    }

    /**
     * Get all objectives for a plan with resource counts.
     *
     * @param int $planId
     * @return Collection
     */
    public function getObjectivesWithLinkCounts(int $planId): Collection
    {
        return StrategyObjective::where('plan_id', $planId)
            ->with('pillar')
            ->get()
            ->each(function ($objective) {
                $objective->link_count = $objective->links()->count();
                $objective->aggregated = $this->getAggregatedContribution($objective->id);
            });
    }
}
