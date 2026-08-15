<?php

namespace Modules\Validation\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Validation\Models\ApprovalHierarchy;
use Modules\Validation\Models\HierarchyLevel;

class HierarchyLevelFactory extends Factory
{
    protected $model = HierarchyLevel::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'hierarchy_id' => ApprovalHierarchy::factory(),
            'level_order' => fake()->numberBetween(1, 5),
            'title' => fake()->randomElement(['Manager', 'Director', 'VP', 'CFO', 'CEO']),
            'approver_count' => fake()->numberBetween(1, 3),
            'delegation_allowed' => fake()->boolean(),
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
