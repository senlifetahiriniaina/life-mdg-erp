<?php

namespace Modules\Workflow\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Workflow\app\Models\WorkflowExecutionStep;

class WorkflowExecutionStepFactory extends Factory
{
    protected $model = WorkflowExecutionStep::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fake()->word(),
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