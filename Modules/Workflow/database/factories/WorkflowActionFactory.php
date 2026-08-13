<?php

namespace Modules\Workflow\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Workflow\app\Models\WorkflowAction;

class WorkflowActionFactory extends Factory
{
    protected $model = WorkflowAction::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'workflow_id' => fake()->word(),
            'order' => fake()->word(),
            'action_type' => fake()->word(),
            'action_config' => fake()->word(),
            'name' => fake()->word(),
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