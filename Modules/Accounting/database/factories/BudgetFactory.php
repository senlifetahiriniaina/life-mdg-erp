<?php

namespace Modules\Accounting\Database\Factories;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\Budget;

class BudgetFactory extends Factory
{
    protected $model = Budget::class;

    public function definition(): array
    {
        $year = (int) $this->faker->year();
        $startDate = Carbon::createFromDate($year, 1, 1);
        $endDate = Carbon::createFromDate($year, 12, 31);

        return [
            'company_id' => 1,
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'budget_period_start' => $startDate,
            'budget_period_end' => $endDate,
            'fiscal_year' => $year,
            'total_budget' => $this->faker->randomFloat(2, 100000, 1000000),
            'status' => 'draft',
        ];
    }
}
