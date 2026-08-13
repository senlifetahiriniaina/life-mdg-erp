<?php

namespace Modules\Reporting\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Reporting\app\Models\ReportExecution;

class ReportExecutionFactory extends Factory
{
    protected $model = ReportExecution::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'tenant_id' => fake()->word(),
            'executed_by' => fake()->word(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'file_path' => fake()->word(),
            'started_at' => fake()->word(),
            'completed_at' => fake()->word(),
            'error_message' => fake()->word(),
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