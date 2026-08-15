<?php

namespace Modules\Strategy\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Strategy\Models\StrategyPillar;
use Modules\Strategy\Models\StrategyPlan;

class StrategyPillarFactory extends Factory
{
    protected $model = StrategyPillar::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'plan_id' => StrategyPlan::factory(),
            'name' => fake()->word(),
            'description' => fake()->text(),
            'color' => fake()->hexColor(),
            'icon' => fake()->word(),
            'sort_order' => fake()->numberBetween(0, 10),
        ];
    }

    /**
     * Indicate model is inactive (no-op: strategy_pillars has no is_active column)
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => []);
    }

    /**
     * Indicate model is archived (no-op: strategy_pillars has no archived_at column)
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => []);
    }
}
