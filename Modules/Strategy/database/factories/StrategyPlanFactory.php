<?php

namespace Modules\Strategy\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Strategy\Models\StrategyPlan;

class StrategyPlanFactory extends Factory
{
    protected $model = StrategyPlan::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fake()->uuid(),
            'name' => fake()->word(),
            'vision' => fake()->paragraph(),
            'mission' => fake()->paragraph(),
            'period_start' => fake()->numberBetween(2024, 2026),
            'period_end' => fake()->numberBetween(2027, 2030),
            'framework' => fake()->randomElement(['okr', 'bsc', 'hoshin', 'hybrid']),
            'status' => fake()->randomElement(['draft', 'active', 'archived']),
            'health_score' => fake()->numberBetween(0, 100),
            'created_by' => User::factory(),
        ];
    }

    /**
     * Indicate model is inactive (no-op: strategy_plans has no is_active column)
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => []);
    }

    /**
     * Indicate model is archived
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'archived',
        ]);
    }
}
