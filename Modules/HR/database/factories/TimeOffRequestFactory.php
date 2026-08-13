<?php

namespace Modules\HR\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\HR\app\Models\TimeOffRequest;

class TimeOffRequestFactory extends Factory
{
    protected $model = TimeOffRequest::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'employee_id' => fake()->word(),
            'request_type' => fake()->word(),
            'start_date' => fake()->dateTime(),
            'end_date' => fake()->dateTime(),
            'duration_days' => fake()->word(),
            'duration_hours' => fake()->word(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'approved_by' => fake()->word(),
            'reason' => fake()->word(),
            'rejection_reason' => fake()->word(),
            'approved_at' => fake()->word(),
            'rejected_at' => fake()->word(),
            'partial_day' => fake()->word(),
            'partial_day_details' => fake()->word(),
            'is_urgent' => fake()->word(),
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