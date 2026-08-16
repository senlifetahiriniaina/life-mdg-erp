<?php

declare(strict_types=1);

namespace Modules\Accounting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\Budget;
use Modules\Accounting\Models\BudgetActual;

/** @extends Factory<BudgetActual> */
class BudgetActualFactory extends Factory
{
    protected $model = BudgetActual::class;

    public function definition(): array
    {
        return [
            'budget_id' => Budget::factory(),
            'budget_line_id' => null,
            'period_month' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-01'),
            'actual_amount' => fake()->randomFloat(2, 0, 100000),
        ];
    }
}
