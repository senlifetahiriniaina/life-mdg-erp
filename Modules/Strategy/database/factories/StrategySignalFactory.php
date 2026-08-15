<?php

namespace Modules\Strategy\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Strategy\Models\StrategySignal;

class StrategySignalFactory extends Factory
{
    protected $model = StrategySignal::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fake()->uuid(),
            'type' => fake()->randomElement(['warning', 'critical', 'info']),
            'source_module' => fake()->randomElement(['Accounting', 'CRM', 'Inventory', 'Sales', 'Helpdesk', 'HR']),
            'source_metric' => fake()->word(),
            'title' => fake()->sentence(4),
            'description' => fake()->text(),
            'recommendation' => fake()->sentence(),
            'impacted_objective_ids' => [fake()->numberBetween(1, 50), fake()->numberBetween(1, 50)],
            'is_read' => fake()->boolean(),
            'is_dismissed' => fake()->boolean(),
            'detected_at' => fake()->dateTime(),
            'expires_at' => fake()->dateTime('+30 days'),
        ];
    }

    /**
     * Indicate model is inactive (no-op: strategy_signals has no is_active column)
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => []);
    }

    /**
     * Indicate model is archived (no-op: strategy_signals has no archived_at column)
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => []);
    }
}
