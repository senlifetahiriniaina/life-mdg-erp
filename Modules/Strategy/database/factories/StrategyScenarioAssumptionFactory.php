<?php

namespace Modules\Strategy\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Strategy\app\Models\StrategyScenarioAssumption;

class StrategyScenarioAssumptionFactory extends Factory
{
    protected $model = StrategyScenarioAssumption::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'scenario_id' => fake()->word(),
            'variable_name' => fake()->word(),
            'description' => fake()->text(),
            'base_value' => fake()->word(),
            'adjusted_value' => fake()->word(),
            'impact_scope' => fake()->word(),
        ];
    }

    /**
     * Indicate model is inactive
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate model is archived
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'archived_at' => now(),
        ]);
    }
}