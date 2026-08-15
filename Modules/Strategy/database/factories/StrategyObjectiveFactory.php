<?php

namespace Modules\Strategy\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Strategy\Models\StrategyObjective;
use Modules\Strategy\Models\StrategyPillar;
use Modules\Strategy\Models\StrategyPlan;

class StrategyObjectiveFactory extends Factory
{
    protected $model = StrategyObjective::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'plan_id' => StrategyPlan::factory(),
            'pillar_id' => StrategyPillar::factory(),
            // parent_id is a nullable self-reference (StrategyObjective::parent()) — left
            // out to avoid recursive factory creation; set explicitly via state() when a
            // child objective is needed.
            'level' => fake()->randomElement(['vision', 'mission', 'strategic', 'annual', 'quarterly', 'team', 'individual']),
            'owner_type' => fake()->randomElement(['company', 'department', 'team', 'user']),
            'owner_id' => fake()->numberBetween(1, 50),
            'title' => fake()->sentence(4),
            'description' => fake()->text(),
            'framework_type' => fake()->randomElement(['okr', 'bsc', 'hoshin', 'smart']),
            'bsc_perspective' => fake()->randomElement(['financial', 'customer', 'process', 'learning']),
            'weight' => fake()->randomFloat(2, 0, 100),
            'start_date' => fake()->date(),
            'end_date' => fake()->date(),
            'status' => fake()->randomElement(['draft', 'active', 'at_risk', 'behind', 'completed', 'cancelled']),
            'progress' => fake()->randomFloat(2, 0, 100),
        ];
    }

    /**
     * Indicate model is inactive (no-op: strategy_objectives has no is_active column)
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => []);
    }

    /**
     * Indicate model is archived (no-op: strategy_objectives has no archived_at column)
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => []);
    }
}
