<?php

declare(strict_types=1);

namespace Modules\Accounting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\Budget;
use Modules\Accounting\Models\BudgetLine;

/** @extends Factory<BudgetLine> */
class BudgetLineFactory extends Factory
{
    protected $model = BudgetLine::class;

    public function definition(): array
    {
        $budgeted = fake()->randomFloat(2, 10000, 500000);
        $actual = fake()->randomFloat(2, 0, 120000);

        return [
            'budget_id' => Budget::factory(),
            'account_id' => null,
            'period_month' => fake()->numberBetween(1, 12),
            'period_year' => 2026,
            'budgeted_amount' => $budgeted,
            'actual_amount' => 0,
            'variance' => round($actual - $budgeted, 2),
            'notes' => fake()->optional()->sentence(),
            'category' => fake()->randomElement(['salaries', 'marketing', 'rent', 'software', 'travel']),
            'description' => fake()->optional()->sentence(),
            'spent_amount' => fake()->randomFloat(2, 0, 100000),
            'period' => 'annual',
            'month' => null,
            'quarter' => null,
        ];
    }
}
