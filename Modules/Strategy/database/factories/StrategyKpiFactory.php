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
            'tenant_id' => fake()->uuid(),
            'name' => fake()->word(),
            'description' => fake()->text(),
            'category' => fake()->word(),
            'source_module' => fake()->randomElement(['Accounting', 'CRM', 'Inventory', 'Sales', 'Helpdesk', 'HR']),
            'source_key' => fake()->word(),
            'source_aggregation' => fake()->randomElement(['sum', 'avg', 'count', 'last', 'custom']),
            'source_filter' => ['field' => fake()->word(), 'operator' => '=', 'value' => fake()->word()],
            'unit' => fake()->word(),
            'frequency' => fake()->randomElement(['realtime', 'daily', 'weekly', 'monthly']),
            'target_value' => fake()->randomFloat(4, 0, 1000),
            'warning_threshold' => fake()->randomFloat(4, 0, 1000),
            'critical_threshold' => fake()->randomFloat(4, 0, 1000),
            'higher_is_better' => fake()->boolean(),
            'is_public' => fake()->boolean(),
        ];
    }

    /**
     * Indicate model is inactive (no-op: strategy_kpis has no is_active column)
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => []);
    }

    /**
     * Indicate model is archived (no-op: strategy_kpis has no archived_at column)
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => []);
    }
}
