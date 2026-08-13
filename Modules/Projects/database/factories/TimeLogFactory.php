<?php

namespace Modules\Projects\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Projects\Models\TimeLog;

class TimeLogFactory extends Factory
{
    protected $model = TimeLog::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'task_id' => fake()->word(),
            'user_id' => fake()->word(),
            'description' => fake()->text(),
            'hourly_rate' => fake()->word(),
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