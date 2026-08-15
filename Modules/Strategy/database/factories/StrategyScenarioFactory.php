<?php

namespace Modules\Strategy\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Strategy\Models\StrategyPlan;
use Modules\Strategy\Models\StrategyScenario;

class StrategyScenarioFactory extends Factory
{
    protected $model = StrategyScenario::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fake()->uuid(),
            'name' => fake()->word(),
            'description' => fake()->text(),
            'type' => fake()->randomElement(['optimistic', 'realistic', 'pessimistic', 'custom']),
            'base_plan_id' => StrategyPlan::factory(),
            'status' => fake()->randomElement(['draft', 'active', 'archived']),
            'probability' => fake()->randomFloat(2, 0, 100),
            'created_by' => User::factory(),
        ];
    }

    /**
     * Indicate model is inactive (no-op: strategy_scenarios has no is_active column)
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
