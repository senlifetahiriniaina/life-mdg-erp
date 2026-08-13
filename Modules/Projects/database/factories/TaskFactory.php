<?php

declare(strict_types=1);

namespace Modules\Projects\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Projects\Models\Task;

/** @extends Factory<Task> */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    public function definition(): array
    {
        return [
            'project_id' => ProjectFactory::new(),
            'title' => fake()->sentence(),
            'description' => fake()->optional()->paragraph(),
            'status' => 'todo',
            'priority' => 'medium',
            'assignee_id' => null,
            'created_by' => User::factory(),
            'due_date' => null,
        ];
    }
}
