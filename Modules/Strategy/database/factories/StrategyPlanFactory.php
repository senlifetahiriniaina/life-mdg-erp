<?php

namespace Modules\Strategy\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Strategy\app\Models\StrategyPlan;

class StrategyPlanFactory extends Factory
{
    protected $model = StrategyPlan::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'tenant_id' => fake()->word(),
            'name' => fake()->word(),
            'vision' => fake()->word(),
            'mission' => fake()->word(),
            'period_start' => fake()->word(),
            'period_end' => fake()->word(),
            'framework' => fake()->word(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'health_score' => fake()->word(),
            'created_by' => fake()->word(),
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