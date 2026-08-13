<?php

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\WorkflowDefinition;

class WorkflowDefinitionFactory extends Factory
{
    protected $model = WorkflowDefinition::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'name' => fake()->word(),
            'module' => fake()->word(),
            'is_active' => true,
            'created_by' => fake()->word(),
            'description' => fake()->text(),
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