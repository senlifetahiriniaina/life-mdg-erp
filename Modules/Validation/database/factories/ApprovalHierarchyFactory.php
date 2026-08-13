<?php

namespace Modules\Validation\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Validation\app\Models\ApprovalHierarchy;

class ApprovalHierarchyFactory extends Factory
{
    protected $model = ApprovalHierarchy::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
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