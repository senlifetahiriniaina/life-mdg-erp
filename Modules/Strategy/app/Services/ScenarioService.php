<?php

namespace Modules\Strategy\Services;

use Modules\Strategy\Models\StrategyScenario;
use Modules\Strategy\Models\StrategyScenarioAssumption;

class ScenarioService
{
    public function createScenario(string $tenantId, array $data, int $userId): StrategyScenario
    {
        $data['tenant_id']  = $tenantId;
        $data['created_by'] = $userId;

        return StrategyScenario::create($data);
    }

    public function addAssumption(int $scenarioId, array $data): StrategyScenarioAssumption
    {
        $data['scenario_id'] = $scenarioId;

        return StrategyScenarioAssumption::create($data);
    }

    /**
     * Apply each assumption and compute impact deltas.
     */
    public function computeImpact(int $scenarioId): array
    {
        $scenario    = StrategyScenario::with('assumptions')->findOrFail($scenarioId);
        $assumptions = $scenario->assumptions;

        $impacts = [];

        foreach ($assumptions as $assumption) {
            $delta    = $assumption->adjusted_value - $assumption->base_value;
            $deltaPct = $assumption->base_value != 0
                ? ($delta / abs($assumption->base_value)) * 100
                : 0;

            $impacts[] = [
                'metric'    => $assumption->variable_name,
                'base'      => $assumption->base_value,
                'adjusted'  => $assumption->adjusted_value,
                'delta'     => $delta,
                'delta_pct' => round($deltaPct, 2),
                'scope'     => $assumption->impact_scope,
            ];
        }

        return [
            'scenario'    => $scenario->toArray(),
            'assumptions' => $assumptions->toArray(),
            'impacts'     => $impacts,
        ];
    }

    /**
     * Side-by-side comparison of multiple scenarios.
     */
    public function compareScenarios(array $scenarioIds): array
    {
        $scenarios = StrategyScenario::with('assumptions')
            ->whereIn('id', $scenarioIds)
            ->get();

        $comparison = [];

        foreach ($scenarios as $scenario) {
            $impact = $this->computeImpact($scenario->id);
            $comparison[] = [
                'scenario_id'   => $scenario->id,
                'scenario_name' => $scenario->name,
                'type'          => $scenario->type,
                'probability'   => $scenario->probability,
                'impacts'       => $impact['impacts'],
            ];
        }

        return $comparison;
    }
}
