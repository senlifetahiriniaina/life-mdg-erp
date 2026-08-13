<?php

namespace Modules\Strategy\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Strategy\Models\StrategyKpiValue;

class StrategyKpiValueFactory extends Factory
{
    protected $model = StrategyKpiValue::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'kpi_id' => fake()->word(),
            'value' => fake()->word(),
            'recorded_at' => fake()->word(),
            'period' => fake()->word(),
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