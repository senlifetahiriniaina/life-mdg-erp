<?php

namespace Modules\Strategy\Services;

use Modules\Strategy\Models\StrategyKeyResult;
use Modules\Strategy\Models\StrategyObjective;
use Modules\Strategy\Models\StrategyPlan;

class OkrService
{
    public function __construct(
        private StrategyPlanService $planService
    ) {}

    public function createObjective(array $data): StrategyObjective
    {
        return StrategyObjective::create($data);
    }

    public function addKeyResult(int $objectiveId, array $data): StrategyKeyResult
    {
        $data['objective_id'] = $objectiveId;
        return StrategyKeyResult::create($data);
    }

    /**
     * Update KR current_value, recalculate progress, bubble up to objective then plan.
     */
    public function updateKeyResultProgress(int $krId, float $currentValue): StrategyKeyResult
    {
        $kr = StrategyKeyResult::findOrFail($krId);
        $kr->current_value = $currentValue;

        // Calculate progress
        $baseline = $kr->baseline_value ?? 0;
        $target   = $kr->target_value;

        if ($target != $baseline) {
            $progress = (($currentValue - $baseline) / ($target - $baseline)) * 100;
        } else {
            $progress = $currentValue >= $target ? 100 : 0;
        }

        $progress = max(0, min(100, round($progress, 2)));
        $kr->progress   = $progress;
        $kr->confidence = $this->computeConfidence($kr, $progress);
        $kr->save();

        // Bubble up: update parent objective
        $objective = $kr->objective;
        if ($objective) {
            $objective->updateProgress();

            // Update plan health score
            $plan = $objective->plan;
            if ($plan) {
                $healthScore = $this->planService->computeHealthScore($plan);
                $plan->update(['health_score' => $healthScore]);
            }
        }

        return $kr->fresh();
    }

    /**
     * Create a child objective linked to a parent.
     */
    public function cascadeObjective(int $objectiveId, array $childData): StrategyObjective
    {
        $parent = StrategyObjective::findOrFail($objectiveId);

        $childData['parent_id'] = $parent->id;
        $childData['plan_id']   = $parent->plan_id;

        return StrategyObjective::create($childData);
    }

    /**
     * Returns nested OKR structure for org-chart style rendering.
     */
    public function getOkrTree(string $tenantId, ?int $planId = null): array
    {
        $plan = $planId
            ? StrategyPlan::forTenant($tenantId)->find($planId)
            : StrategyPlan::forTenant($tenantId)->active()->first();

        if (! $plan) {
            return [
                'plan_id'    => null,
                'plan_name'  => null,
                'objectives' => [],
            ];
        }

        $planId = $plan->id;

        $objectives = StrategyObjective::where('plan_id', $planId)
            ->whereNull('parent_id')
            ->with(['keyResults', 'children' => function ($query) {
                $query->with(['keyResults', 'children.keyResults']);
            }])
            ->get();

        return [
            'plan_id'    => $planId,
            'plan_name'  => $plan->name,
            'objectives' => $objectives->toArray(),
        ];
    }

    /**
     * Determine confidence based on progress vs expected progress.
     */
    public function computeConfidence(StrategyKeyResult $kr, ?float $progress = null): string
    {
        $progress = $progress ?? $kr->progress;

        // Simple heuristic: compare progress to expected based on date range
        $expected = 50.0; // Default midpoint if no dates

        if ($kr->objective?->start_date && $kr->objective?->end_date) {
            $start   = $kr->objective->start_date->timestamp;
            $end     = $kr->objective->end_date->timestamp;
            $now     = now()->timestamp;
            $elapsed = $end > $start ? (($now - $start) / ($end - $start)) * 100 : 50;
            $expected = max(0, min(100, $elapsed));
        }

        $gap = $progress - $expected;

        if ($gap >= -15) {
            return 'on_track';
        } elseif ($gap >= -30) {
            return 'at_risk';
        } else {
            return 'behind';
        }
    }
}
