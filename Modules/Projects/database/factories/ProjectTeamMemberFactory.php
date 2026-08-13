<?php

namespace Modules\Projects\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Projects\Models\ProjectTeamMember;

class ProjectTeamMemberFactory extends Factory
{
    protected $model = ProjectTeamMember::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'project_id' => fake()->word(),
            'user_id' => fake()->word(),
            'role' => fake()->word(),
            'can_edit_tasks' => fake()->word(),
            'can_manage_members' => fake()->word(),
            'joined_at' => fake()->word(),
            'left_at' => fake()->word(),
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