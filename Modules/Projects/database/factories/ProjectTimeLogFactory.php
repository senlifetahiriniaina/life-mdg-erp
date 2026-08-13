<?php

namespace Modules\Projects\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Projects\app\Models\ProjectTimeLog;

class ProjectTimeLogFactory extends Factory
{
    protected $model = ProjectTimeLog::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'project_id' => fake()->word(),
            'task_id' => fake()->word(),
            'user_id' => fake()->word(),
            'started_at' => fake()->word(),
            'ended_at' => fake()->word(),
            'duration_minutes' => fake()->word(),
            'description' => fake()->text(),
            'billable' => fake()->word(),
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