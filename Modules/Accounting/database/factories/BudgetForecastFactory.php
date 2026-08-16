<?php

declare(strict_types=1);

namespace Modules\Accounting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\Budget;
use Modules\Accounting\Models\BudgetForecast;
use Modules\Accounting\Models\BudgetLine;

/** @extends Factory<BudgetForecast> */
class BudgetForecastFactory extends Factory
{
    protected $model = BudgetForecast::class;

    public function definition(): array
    {
        return [
            'budget_id' => Budget::factory(),
            'budget_line_id' => BudgetLine::factory(),
            'forecast_month' => fake()->dateTimeBetween('now', '+6 months')->format('Y-m-01'),
            'forecasted_amount' => fake()->randomFloat(2, 0, 100000),
            'forecast_method' => 'linear',
            'confidence_level' => 0.85,
        ];
    }
}
