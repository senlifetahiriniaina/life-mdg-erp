<?php

namespace Modules\Validation\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Validation\Models\HierarchyLevel;
use Modules\Validation\Models\LevelApprover;

class LevelApproverFactory extends Factory
{
    protected $model = LevelApprover::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'hierarchy_level_id' => HierarchyLevel::factory(),
            'user_id' => User::factory(),
            'approver_order' => fake()->numberBetween(1, 5),
            'is_active' => fake()->boolean(90),
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
