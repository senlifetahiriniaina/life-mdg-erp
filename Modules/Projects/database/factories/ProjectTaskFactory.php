<?php

namespace Modules\Projects\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Projects\app\Models\ProjectTask;

class ProjectTaskFactory extends Factory
{
    protected $model = ProjectTask::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'project_id' => fake()->word(),
            'milestone_id' => fake()->word(),
            'parent_id' => fake()->word(),
            'assignee_id' => fake()->word(),
            'created_by' => fake()->word(),
            'epic_id' => fake()->word(),
            'sprint_id' => fake()->word(),
            'title' => fake()->word(),
            'description' => fake()->text(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'priority' => fake()->word(),
            'estimated_hours' => fake()->word(),
            'logged_hours' => fake()->word(),
            'story_points' => fake()->word(),
            'start_date' => fake()->dateTime(),
            'due_date' => fake()->dateTime(),
            'completed_at' => fake()->word(),
            'tags' => fake()->word(),
            'dependencies' => fake()->word(),
            'sequence' => fake()->word(),
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