<?php

declare(strict_types=1);

namespace Modules\Accounting\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\CashFlowForecast;

/** @extends Factory<CashFlowForecast> */
class CashFlowForecastFactory extends Factory
{
    protected $model = CashFlowForecast::class;

    public function definition(): array
    {
        $baseDate = fake()->dateTimeBetween('-3 months', 'now');
        $horizon = fake()->randomElement(['30d', '60d', '90d', 'custom']);
        $days = match ($horizon) {
            '30d' => 30,
            '60d' => 60,
            '90d' => 90,
            default => fake()->numberBetween(14, 180),
        };
        $endDate = (clone $baseDate)->modify("+{$days} days");
        $opening = fake()->randomFloat(2, 5000, 500000);
        $projected = fake()->randomFloat(2, 1000, $opening * 1.5);
        $minThreshold = fake()->randomFloat(2, 500, $opening * 0.2);

        return [
            'created_by' => User::factory(),
            'name' => fake()->words(3, true).' forecast',
            'description' => fake()->optional()->sentence(),
            'base_date' => $baseDate->format('Y-m-d'),
            'horizon' => $horizon,
            'end_date' => $endDate->format('Y-m-d'),
            'scenario' => fake()->randomElement(['base', 'optimistic', 'pessimistic']),
            'opening_balance' => $opening,
            'projected_closing_balance' => $projected,
            'minimum_balance_threshold' => $minThreshold,
            'assumptions' => [
                'revenue_growth_rate' => fake()->randomFloat(2, -5, 20),
                'expense_growth_rate' => fake()->randomFloat(2, -2, 15),
                'notes' => fake()->sentence(),
            ],
            'status' => fake()->randomElement(['draft', 'active', 'archived']),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'draft']);
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'active']);
    }
}
