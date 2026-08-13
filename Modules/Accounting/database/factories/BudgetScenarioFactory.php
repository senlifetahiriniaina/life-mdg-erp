<?php

declare(strict_types=1);

namespace Modules\Accounting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\Budget;
use Modules\Accounting\Models\BudgetScenario;

/** @extends Factory<BudgetScenario> */
class BudgetScenarioFactory extends Factory
{
    protected $model = BudgetScenario::class;

    public function definition(): array
    {
        return [
            'name' => 'Optimistic Scenario',
            'base_budget_id' => Budget::factory(),
            'scenario_type' => 'optimistic',
            'adjustment_type' => 'percentage',
            'revenue_adjustment' => 0.15,
            'expense_adjustment' => -0.10,
            'description' => fake()->optional()->sentence(),
            'assumptions' => null,
        ];
    }

    public function pessimistic(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Pessimistic Scenario',
            'scenario_type' => 'pessimistic',
            'revenue_adjustment' => -0.15,
            'expense_adjustment' => 0.10,
        ]);
    }

    public function stress(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Stress Test Scenario',
            'scenario_type' => 'stress',
            'revenue_adjustment' => -0.30,
            'expense_adjustment' => 0.20,
        ]);
    }
}
