<?php

namespace Modules\Strategy\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Strategy\Models\StrategyScenario;
use Modules\Strategy\Models\StrategyScenarioAssumption;

class StrategyScenarioAssumptionFactory extends Factory
{
    protected $model = StrategyScenarioAssumption::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'scenario_id' => StrategyScenario::factory(),
            'variable_name' => fake()->word(),
            'description' => fake()->text(),
            'base_value' => fake()->randomFloat(4, 0, 100000),
            'adjusted_value' => fake()->randomFloat(4, 0, 100000),
            'impact_scope' => fake()->word(),
        ];
    }

    /**
     * Indicate model is inactive (no-op: strategy_scenario_assumptions has no is_active column)
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => []);
    }

    /**
     * Indicate model is archived (no-op: strategy_scenario_assumptions has no archived_at column)
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => []);
    }
}
