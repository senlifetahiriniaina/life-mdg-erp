<?php

namespace Modules\Strategy\Services;

use Modules\Strategy\Models\StrategyPlan;
use Modules\Strategy\Models\StrategyObjective;

class StrategyPlanService
{
    public function createPlan(string $tenantId, array $data, int $userId): StrategyPlan
    {
        $data['tenant_id'] = $tenantId;
        $data['created_by'] = $userId;

        $plan = StrategyPlan::create($data);

        return $plan;
    }

    public function updatePlan(int $planId, array $data): StrategyPlan
    {
        $plan = StrategyPlan::findOrFail($planId);
        $plan->update($data);

        return $plan->fresh();
    }

    /**
     * Compute health score as weighted average of all active objectives' progress, capped 0-100.
     */
    public function computeHealthScore(StrategyPlan $plan): int
    {
        $objectives = $plan->objectives()
            ->whereIn('status', ['active', 'at_risk', 'behind'])
            ->get();

        if ($objectives->isEmpty()) {
            return 0;
        }

        $totalWeight = $objectives->sum('weight');

        if ($totalWeight == 0) {
            return 0;
        }

        $weightedSum = $objectives->sum(fn ($obj) => $obj->progress * $obj->weight);
        $score = (int) round(($weightedSum / $totalWeight));

        return max(0, min(100, $score));
    }

    /**
     * Returns plan with pillars → objectives → keyResults, nested.
     */
    public function getFullTree(int $planId): array
    {
        $plan = StrategyPlan::with([
            'pillars',
            'objectives' => function ($query) {
                $query->whereNull('parent_id')->with(['keyResults', 'children.keyResults']);
            },
        ])->findOrFail($planId);

        return $plan->toArray();
    }

    /**
     * Duplicate a plan with a new name.
     */
    public function duplicatePlan(int $planId, string $newName): StrategyPlan
    {
        $original = StrategyPlan::with(['pillars', 'objectives.keyResults'])->findOrFail($planId);

        $newPlan = $original->replicate();
        $newPlan->name = $newName;
        $newPlan->status = 'draft';
        $newPlan->health_score = 0;
        $newPlan->save();

        // Duplicate pillars
        $pillarMap = [];
        foreach ($original->pillars as $pillar) {
            $newPillar = $pillar->replicate();
            $newPillar->plan_id = $newPlan->id;
            $newPillar->save();
            $pillarMap[$pillar->id] = $newPillar->id;
        }

        // Duplicate top-level objectives
        foreach ($original->objectives->whereNull('parent_id') as $objective) {
            $newObjective = $objective->replicate();
            $newObjective->plan_id = $newPlan->id;
            $newObjective->pillar_id = $objective->pillar_id
                ? ($pillarMap[$objective->pillar_id] ?? null)
                : null;
            $newObjective->progress = 0;
            $newObjective->status = 'draft';
            $newObjective->save();

            foreach ($objective->keyResults as $kr) {
                $newKr = $kr->replicate();
                $newKr->objective_id = $newObjective->id;
                $newKr->current_value = $newKr->baseline_value ?? 0;
                $newKr->progress = 0;
                $newKr->save();
            }
        }

        return $newPlan->fresh();
    }
}
