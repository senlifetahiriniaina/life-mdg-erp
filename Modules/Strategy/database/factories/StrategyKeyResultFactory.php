<?php

namespace Modules\Strategy\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Strategy\Models\StrategyKeyResult;
use Modules\Strategy\Models\StrategyObjective;

class StrategyKeyResultFactory extends Factory
{
    protected $model = StrategyKeyResult::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'objective_id' => StrategyObjective::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->text(),
            'type' => fake()->randomElement(['percentage', 'number', 'currency', 'boolean', 'milestone']),
            'baseline_value' => fake()->randomFloat(2, 0, 1000),
            'target_value' => fake()->randomFloat(2, 0, 1000),
            'current_value' => fake()->randomFloat(2, 0, 1000),
            'unit' => fake()->word(),
            'data_source_module' => fake()->randomElement(['Accounting', 'CRM', 'Inventory', 'Sales', 'Helpdesk', 'HR']),
            'data_source_key' => fake()->word(),
            'progress' => fake()->randomFloat(2, 0, 100),
            'confidence' => fake()->randomFloat(2, 0, 100),
        ];
    }

    /**
     * Indicate model is inactive (no-op: strategy_key_results has no is_active column)
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => []);
    }

    /**
     * Indicate model is archived (no-op: strategy_key_results has no archived_at column)
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => []);
    }
}
