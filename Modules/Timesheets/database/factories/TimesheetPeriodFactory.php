<?php

namespace Modules\Timesheets\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Timesheets\app\Models\TimesheetPeriod;

class TimesheetPeriodFactory extends Factory
{
    protected $model = TimesheetPeriod::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'tenant_id' => fake()->word(),
            'employee_id' => fake()->word(),
            'period_start' => fake()->word(),
            'period_end' => fake()->word(),
            'total_hours' => fake()->word(),
            'billable_hours' => fake()->word(),
            'overtime_hours' => fake()->word(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'submitted_by' => fake()->word(),
            'submitted_at' => fake()->word(),
            'approved_by' => fake()->word(),
            'approved_at' => fake()->word(),
            'rejected_reason' => fake()->word(),
            'name' => fake()->word(),
            'title' => fake()->word(),
            'description' => fake()->text(),
            'slug' => fake()->slug(),
            'code' => fake()->bothify('??-##'),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'amount' => fake()->randomFloat(2, 0, 1000),
            'quantity' => fake()->numberBetween(1, 100),
            'price' => fake()->randomFloat(2, 0, 1000),
            'cost' => fake()->randomFloat(2, 0, 1000),
            'is_active' => true,
            'notes' => fake()->text(),
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