<?php

namespace Modules\HR\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\HR\app\Models\AttendanceException;

class AttendanceExceptionFactory extends Factory
{
    protected $model = AttendanceException::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'employee_id' => fake()->word(),
            'attendance_date' => fake()->word(),
            'exception_type' => fake()->word(),
            'minutes_late' => fake()->word(),
            'minutes_early' => fake()->word(),
            'reason' => fake()->word(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'approved_by' => fake()->word(),
            'manager_notes' => fake()->word(),
            'employee_response' => fake()->word(),
            'acknowledged_at' => fake()->word(),
            'approved_at' => fake()->word(),
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