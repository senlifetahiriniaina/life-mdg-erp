<?php

namespace Modules\Strategy\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Strategy\Models\Correlation;

class CorrelationFactory extends Factory
{
    protected $model = Correlation::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'kpi_a' => fake()->randomElement(['CRM', 'Accounting', 'HR', 'Inventory', 'Sales', 'Helpdesk']) . ':' . fake()->word(),
            'kpi_b' => fake()->randomElement(['CRM', 'Accounting', 'HR', 'Inventory', 'Sales', 'Helpdesk']) . ':' . fake()->word(),
            'coefficient' => fake()->randomFloat(4, -1, 1),
            'lag_periods' => fake()->numberBetween(0, 12),
            'confidence' => fake()->randomFloat(2, 0, 100),
            'last_computed_at' => fake()->dateTime(),
        ];
    }

    /**
     * Indicate model is inactive (no-op: strategy_correlations has no is_active column)
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => []);
    }

    /**
     * Indicate model is archived (no-op: strategy_correlations has no archived_at column)
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => []);
    }
}
