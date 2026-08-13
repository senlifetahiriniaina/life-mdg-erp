<?php

declare(strict_types=1);

namespace Modules\Projects\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Projects\Models\TaskDependency;

/** @extends Factory<TaskDependency> */
class TaskDependencyFactory extends Factory
{
    protected $model = TaskDependency::class;

    public function definition(): array
    {
        return [
            'task_id' => TaskFactory::new(),
            'depends_on_task_id' => TaskFactory::new(),
            'type' => fake()->randomElement(['FS', 'SS', 'FF', 'SF']),
            'lag_days' => fake()->numberBetween(0, 5),
        ];
    }

    public function finishToStart(): static
    {
        return $this->state(['type' => 'FS']);
    }
}
