<?php

namespace Modules\Workflow\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Workflow\Models\WorkflowExecution;

class WorkflowExecutionFactory extends Factory
{
    protected $model = WorkflowExecution::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'tenant_id' => fake()->word(),
            'workflow_id' => fake()->word(),
            'trigger_data' => fake()->word(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
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