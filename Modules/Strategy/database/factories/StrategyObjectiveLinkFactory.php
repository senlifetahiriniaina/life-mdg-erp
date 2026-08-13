<?php

namespace Modules\Strategy\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Strategy\Models\StrategyObjectiveLink;

class StrategyObjectiveLinkFactory extends Factory
{
    protected $model = StrategyObjectiveLink::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'strategy_objective_id' => fake()->word(),
            'linkable_type' => fake()->word(),
            'linkable_id' => fake()->word(),
            'contribution_value' => fake()->word(),
            'unit_type' => fake()->word(),
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