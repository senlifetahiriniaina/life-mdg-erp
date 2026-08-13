<?php

namespace Modules\Strategy\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Strategy\Models\StrategyKpi;

class StrategyKpiFactory extends Factory
{
    protected $model = StrategyKpi::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'tenant_id' => fake()->word(),
            'name' => fake()->word(),
            'description' => fake()->text(),
            'category' => fake()->word(),
            'source_module' => fake()->word(),
            'source_key' => fake()->word(),
            'source_aggregation' => fake()->word(),
            'source_filter' => fake()->word(),
            'unit' => fake()->word(),
            'frequency' => fake()->word(),
            'target_value' => fake()->word(),
            'warning_threshold' => fake()->word(),
            'critical_threshold' => fake()->word(),
            'higher_is_better' => fake()->word(),
            'is_public' => fake()->word(),
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