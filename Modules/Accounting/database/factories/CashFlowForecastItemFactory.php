<?php

declare(strict_types=1);

namespace Modules\Accounting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\CashFlowForecast;
use Modules\Accounting\Models\CashFlowForecastItem;

/** @extends Factory<CashFlowForecastItem> */
class CashFlowForecastItemFactory extends Factory
{
    protected $model = CashFlowForecastItem::class;

    public function definition(): array
    {
        $amount = fake()->randomFloat(2, 100, 50000);
        $probability = fake()->randomFloat(2, 0.10, 1.00);

        return [
            'forecast_id' => CashFlowForecast::factory(),
            'date' => fake()->dateTimeBetween('now', '+90 days')->format('Y-m-d'),
            'category' => fake()->randomElement([
                'sales_revenue', 'service_revenue', 'payroll', 'rent',
                'utilities', 'tax_payment', 'loan_repayment', 'capex',
                'dividends', 'other_income', 'other_expense',
            ]),
            'type' => fake()->randomElement(['inflow', 'outflow']),
            'source' => fake()->optional()->company(),
            'description' => fake()->optional()->sentence(),
            'amount' => $amount,
            'probability' => $probability,
            'weighted_amount' => round($amount * $probability, 2),
            'is_actual' => fake()->boolean(20),
            'reference_type' => fake()->optional(0.3)->randomElement([
                'App\Models\Invoice', 'App\Models\PurchaseOrder', null,
            ]),
            'reference_id' => fake()->optional(0.3)->numberBetween(1, 1000),
        ];
    }

    public function inflow(): static
    {
        return $this->state(fn (array $attributes) => ['type' => 'inflow']);
    }

    public function outflow(): static
    {
        return $this->state(fn (array $attributes) => ['type' => 'outflow']);
    }

    public function actual(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_actual' => true,
            'probability' => 1.00,
            'weighted_amount' => $attributes['amount'],
        ]);
    }
}
