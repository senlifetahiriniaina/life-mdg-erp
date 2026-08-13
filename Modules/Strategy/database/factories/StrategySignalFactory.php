<?php

namespace Modules\Strategy\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Strategy\app\Models\StrategySignal;

class StrategySignalFactory extends Factory
{
    protected $model = StrategySignal::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'tenant_id' => fake()->word(),
            'type' => fake()->word(),
            'source_module' => fake()->word(),
            'source_metric' => fake()->word(),
            'title' => fake()->word(),
            'description' => fake()->text(),
            'recommendation' => fake()->word(),
            'impacted_objective_ids' => fake()->word(),
            'is_read' => fake()->word(),
            'is_dismissed' => fake()->word(),
            'detected_at' => fake()->word(),
            'expires_at' => fake()->word(),
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