<?php

declare(strict_types=1);

namespace Modules\Accounting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\CashFlowLine;
use Modules\Accounting\Models\TreasuryForecast;

/** @extends Factory<CashFlowLine> */
class CashFlowLineFactory extends Factory
{
    protected $model = CashFlowLine::class;

    public function definition(): array
    {
        return [
            'forecast_id' => TreasuryForecast::factory(),
            'category' => fake()->randomElement([
                'sales_revenue',
                'service_revenue',
                'investment',
                'loan_proceeds',
                'other_income',
                'salaries',
                'rent',
                'utilities',
                'marketing',
                'loan_repayment',
                'tax_payment',
                'other_expense',
            ]),
            'flow_type' => 'inflow',
            'amount' => fake()->randomFloat(2, 1000, 50000),
            'description' => fake()->optional()->sentence(),
            'expected_date' => now()->addDays(fake()->numberBetween(1, 30))->toDateString(),
            'is_recurring' => false,
            'recurrence_period' => null,
            'probability' => 100,
            'actual_amount' => null,
        ];
    }

    public function inflow(): static
    {
        return $this->state(fn (array $attributes) => ['flow_type' => 'inflow']);
    }

    public function outflow(): static
    {
        return $this->state(fn (array $attributes) => [
            'flow_type' => 'outflow',
            'category' => fake()->randomElement(['salaries', 'rent', 'utilities', 'marketing', 'other_expense']),
        ]);
    }
}
