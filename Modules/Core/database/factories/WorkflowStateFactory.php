<?php

namespace Modules\Core\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\app\Models\WorkflowState;

class WorkflowStateFactory extends Factory
{
    protected $model = WorkflowState::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'workflow_definition_id' => fake()->word(),
            'subject_type' => fake()->word(),
            'subject_id' => fake()->word(),
            'current_step' => fake()->word(),
            'metadata' => fake()->word(),
            'started_at' => fake()->word(),
            'completed_at' => fake()->word(),
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