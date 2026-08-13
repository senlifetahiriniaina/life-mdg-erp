<?php

namespace Modules\Workflow\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Workflow\app\Models\WorkflowExecutionLog;

class WorkflowExecutionLogFactory extends Factory
{
    protected $model = WorkflowExecutionLog::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'execution_id' => fake()->word(),
            'action_id' => fake()->word(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'result' => fake()->word(),
            'executed_at' => fake()->word(),
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