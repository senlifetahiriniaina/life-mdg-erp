<?php

declare(strict_types=1);

namespace Modules\Accounting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\ExpenseReport;

/** @extends Factory<ExpenseReport> */
class ExpenseReportFactory extends Factory
{
    protected $model = ExpenseReport::class;

    public function definition(): array
    {
        return [
            'employee_id' => null,
            'title' => fake()->sentence(3),
            'status' => fake()->randomElement(['draft', 'submitted', 'approved', 'rejected', 'paid']),
            'total' => fake()->randomFloat(2, 50, 5000),
            'submitted_at' => null,
            'approved_at' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
            'submitted_at' => null,
            'approved_at' => null,
        ]);
    }

    public function submitted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'submitted',
            'submitted_at' => now(),
            'approved_at' => null,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'submitted_at' => now()->subDay(),
            'approved_at' => now(),
        ]);
    }
}
