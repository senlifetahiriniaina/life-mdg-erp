<?php

namespace Modules\HR\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\HR\app\Models\ShiftSchedule;

class ShiftScheduleFactory extends Factory
{
    protected $model = ShiftSchedule::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'employee_id' => fake()->word(),
            'shift_name' => fake()->word(),
            'shift_code' => fake()->word(),
            'start_time' => fake()->word(),
            'end_time' => fake()->word(),
            'working_hours' => fake()->word(),
            'days_of_week' => fake()->word(),
            'is_night_shift' => fake()->word(),
            'is_flexible' => fake()->word(),
            'effective_from' => fake()->word(),
            'effective_to' => fake()->word(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'notes' => fake()->text(),
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