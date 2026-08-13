<?php

declare(strict_types=1);

namespace Modules\Accounting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\ExpenseLine;
use Modules\Accounting\Models\ExpenseReport;

/** @extends Factory<ExpenseLine> */
class ExpenseLineFactory extends Factory
{
    protected $model = ExpenseLine::class;

    public function definition(): array
    {
        return [
            'report_id' => ExpenseReport::factory(),
            'date' => fake()->dateTimeBetween('-60 days', 'now')->format('Y-m-d'),
            'category' => fake()->randomElement(['Transport', 'Repas', 'Hébergement', 'Fournitures', 'Autres charges']),
            'description' => fake()->optional()->sentence(),
            'amount' => fake()->randomFloat(2, 5, 500),
            'currency' => 'EUR',
            'receipt_url' => null,
            'km' => null,
        ];
    }

    public function mileage(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'Transport',
            'km' => fake()->randomFloat(1, 10, 500),
        ]);
    }
}
