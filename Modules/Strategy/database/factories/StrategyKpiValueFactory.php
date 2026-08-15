<?php

namespace Modules\Strategy\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Strategy\Models\StrategyKpi;
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
            'kpi_id' => StrategyKpi::factory(),
            'value' => fake()->randomFloat(4, 0, 100000),
            'recorded_at' => fake()->dateTime(),
            'period' => fake()->date('Y-m'),
        ];
    }

    /**
     * Indicate model is inactive (no-op: strategy_kpi_values has no is_active column)
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => []);
    }

    /**
     * Indicate model is archived (no-op: strategy_kpi_values has no archived_at column)
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => []);
    }
}
