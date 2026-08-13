<?php

namespace Modules\CRM\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\CRM\app\Models\WorkflowNode;

class WorkflowNodeFactory extends Factory
{
    protected $model = WorkflowNode::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'workflow_id' => fake()->word(),
            'node_id' => fake()->word(),
            'type' => fake()->word(),
            'name' => fake()->word(),
            'config' => fake()->word(),
            'position_x' => fake()->word(),
            'position_y' => fake()->word(),
            'title' => fake()->word(),
            'description' => fake()->text(),
            'slug' => fake()->slug(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'code' => fake()->bothify('??-##'),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'amount' => fake()->randomFloat(2, 0, 1000),
            'quantity' => fake()->numberBetween(1, 100),
            'price' => fake()->randomFloat(2, 0, 1000),
            'cost' => fake()->randomFloat(2, 0, 1000),
            'is_active' => true,
            'notes' => fake()->text(),
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
            'archived_at' => now(),
        ]);
    }
}