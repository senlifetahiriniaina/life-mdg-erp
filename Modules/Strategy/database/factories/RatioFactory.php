<?php

namespace Modules\Strategy\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Strategy\Models\Ratio;
use Modules\Strategy\Models\StrategyKpi;

class RatioFactory extends Factory
{
    protected $model = Ratio::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'module' => fake()->randomElement(['Accounting', 'CRM', 'Inventory', 'Sales', 'Helpdesk', 'HR']),
            'name' => fake()->word(),
            'numerator_kpi_id' => StrategyKpi::factory(),
            'denominator_kpi_id' => StrategyKpi::factory(),
            'formula' => fake()->word(),
            'benchmark_category' => fake()->word(),
            'description' => fake()->text(),
            'unit' => fake()->word(),
            'direction' => fake()->randomElement(['up', 'down', 'target']),
            'target_min' => fake()->randomFloat(4, 0, 50),
            'target_max' => fake()->randomFloat(4, 50, 100),
        ];
    }

    /**
     * Indicate model is inactive (no-op: strategy_ratios has no is_active column)
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => []);
    }

    /**
     * Indicate model is archived (no-op: strategy_ratios has no archived_at column)
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => []);
    }
}
