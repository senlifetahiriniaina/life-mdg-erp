<?php

namespace Modules\Strategy\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Strategy\app\Models\StrategyKeyResult;

class StrategyKeyResultFactory extends Factory
{
    protected $model = StrategyKeyResult::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'objective_id' => fake()->word(),
            'title' => fake()->word(),
            'description' => fake()->text(),
            'type' => fake()->word(),
            'baseline_value' => fake()->word(),
            'target_value' => fake()->word(),
            'current_value' => fake()->word(),
            'unit' => fake()->word(),
            'data_source_module' => fake()->word(),
            'data_source_key' => fake()->word(),
            'progress' => fake()->word(),
            'confidence' => fake()->word(),
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