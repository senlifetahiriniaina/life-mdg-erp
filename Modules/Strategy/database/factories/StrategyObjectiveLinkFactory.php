<?php

namespace Modules\Strategy\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Strategy\Models\StrategyObjective;
use Modules\Strategy\Models\StrategyObjectiveLink;

class StrategyObjectiveLinkFactory extends Factory
{
    protected $model = StrategyObjectiveLink::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'strategy_objective_id' => StrategyObjective::factory(),
            // linkable_type/linkable_id form a lightweight custom polymorphic pointer
            // (see StrategyObjectiveLink::getResourceClass(), format "Module/Model")
            // rather than a real Eloquent morph relation, so linkable_id is a plain
            // integer rather than a factory-created related record.
            'linkable_type' => fake()->randomElement(['Accounting/Invoice', 'CRM/Opportunity', 'Sales/SalesOrder', 'Inventory/Product']),
            'linkable_id' => fake()->numberBetween(1, 100),
            'contribution_value' => fake()->randomFloat(4, 0, 1000),
            'unit_type' => fake()->word(),
        ];
    }

    /**
     * Indicate model is inactive (no-op: strategy_objective_links has no is_active column)
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => []);
    }

    /**
     * Indicate model is archived (no-op: strategy_objective_links has no archived_at column)
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => []);
    }
}
