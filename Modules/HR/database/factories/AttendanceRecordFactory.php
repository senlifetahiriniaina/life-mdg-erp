<?php

namespace Modules\HR\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\HR\Models\AttendanceRecord;

class AttendanceRecordFactory extends Factory
{
    protected $model = AttendanceRecord::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'employee_id' => fake()->word(),
            'clock_in' => fake()->word(),
            'clock_out' => fake()->word(),
            'type' => fake()->word(),
            'ip_address' => fake()->word(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
        ];
    }

    /**
     * Indicate model is inactive
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
        ]);
    }

    /**
     * Indicate model is archived
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
        ]);
    }
}