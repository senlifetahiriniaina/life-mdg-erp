<?php

namespace Modules\Workflow\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Workflow\Models\WorkflowChainDefinition;

class WorkflowChainDefinitionFactory extends Factory
{
    protected $model = WorkflowChainDefinition::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'tenant_id' => fake()->word(),
            'trigger_key' => fake()->word(),
            'is_active' => true,
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