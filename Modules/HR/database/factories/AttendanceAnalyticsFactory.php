<?php

namespace Modules\HR\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\HR\app\Models\AttendanceAnalytics;

class AttendanceAnalyticsFactory extends Factory
{
    protected $model = AttendanceAnalytics::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'employee_id' => fake()->word(),
            'analytics_date' => fake()->word(),
            'month' => fake()->word(),
            'year' => fake()->word(),
            'days_present' => fake()->word(),
            'days_absent' => fake()->word(),
            'days_late' => fake()->word(),
            'days_early_departure' => fake()->word(),
            'total_late_minutes' => fake()->word(),
            'total_early_minutes' => fake()->word(),
            'total_working_minutes' => fake()->word(),
            'total_expected_minutes' => fake()->word(),
            'attendance_percentage' => fake()->word(),
            'punctuality_percentage' => fake()->word(),
            'trend' => fake()->word(),
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