<?php

namespace Modules\Projects\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\User;
use Modules\Projects\Models\Project;
use Modules\Projects\Models\ProjectTimeLog;
use Modules\Projects\Models\Task;

class ProjectTimeLogFactory extends Factory
{
    protected $model = ProjectTimeLog::class;

    public function definition(): array
    {
        $startedAt = fake()->dateTimeBetween('-1 month', 'now');
        $durationMinutes = fake()->numberBetween(15, 480);

        return [
            'project_id' => Project::factory(),
            'task_id' => Task::factory(),
            'user_id' => User::factory(),
            'started_at' => $startedAt,
            'ended_at' => (clone $startedAt)->modify("+{$durationMinutes} minutes"),
            'duration_minutes' => $durationMinutes,
            'description' => fake()->optional()->sentence(),
            'billable' => fake()->boolean(60),
            'hourly_rate' => fake()->randomFloat(2, 10, 150),
        ];
    }
}
