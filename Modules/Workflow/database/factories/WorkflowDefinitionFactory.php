<?php

namespace Modules\Workflow\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Workflow\app\Models\WorkflowDefinition;

class WorkflowDefinitionFactory extends Factory
{
    protected $model = WorkflowDefinition::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'tenant_id' => fake()->word(),
            'name' => fake()->word(),
            'description' => fake()->text(),
            'module' => fake()->word(),
            'trigger_event' => fake()->word(),
            'trigger_conditions' => fake()->word(),
            'is_active' => true,
            'created_by' => fake()->word(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
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
        ]);
    }
}