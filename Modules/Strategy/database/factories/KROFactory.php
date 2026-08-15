<?php

namespace Modules\Strategy\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Strategy\Models\KRO;
use Modules\Strategy\Models\StrategyKpi;
use Modules\Strategy\Models\StrategyObjective;

class KROFactory extends Factory
{
    protected $model = KRO::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'objective_id' => StrategyObjective::factory(),
            'kpi_id' => StrategyKpi::factory(),
            'target' => fake()->randomFloat(4, 0, 1000),
            'baseline' => fake()->randomFloat(4, 0, 1000),
            'current' => fake()->randomFloat(4, 0, 1000),
            'weight' => fake()->randomFloat(2, 0, 100),
        ];
    }

    /**
     * Indicate model is inactive (no-op: strategy_kros has no is_active column)
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => []);
    }

    /**
     * Indicate model is archived (no-op: strategy_kros has no archived_at column)
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => []);
    }
}
