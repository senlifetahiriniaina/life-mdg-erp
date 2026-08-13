<?php

namespace Modules\HR\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\HR\app\Models\EmployeeCompensation;

class EmployeeCompensationFactory extends Factory
{
    protected $model = EmployeeCompensation::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'employee_id' => fake()->word(),
            'base_salary' => fake()->word(),
            'currency' => fake()->word(),
            'bonus_amount' => fake()->word(),
            'bonus_frequency' => fake()->word(),
            'equity_granted' => fake()->word(),
            'equity_vesting_period_months' => fake()->word(),
            'equity_vesting_schedule' => fake()->word(),
            'equity_vested_percentage' => fake()->word(),
            'benefits_annual_value' => fake()->word(),
            'total_compensation' => fake()->word(),
            'effective_date' => fake()->word(),
            'end_date' => fake()->dateTime(),
            'notes' => fake()->text(),
            'name' => fake()->word(),
            'title' => fake()->word(),
            'description' => fake()->text(),
            'slug' => fake()->slug(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'code' => fake()->bothify('??-##'),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'amount' => fake()->randomFloat(2, 0, 1000),
            'quantity' => fake()->numberBetween(1, 100),
            'price' => fake()->randomFloat(2, 0, 1000),
            'cost' => fake()->randomFloat(2, 0, 1000),
            'is_active' => true,
        ];
    }

    /**
     * Indicate model is inactive
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate model is archived
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'archived_at' => now(),
        ]);
    }
}