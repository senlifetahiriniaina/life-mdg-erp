<?php

declare(strict_types=1);

namespace Modules\Timesheets\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Projects\Models\Project;
use Modules\Timesheets\Models\TimeTrackingProject;

/** @extends Factory<TimeTrackingProject> */
class TimeTrackingProjectFactory extends Factory
{
    protected $model = TimeTrackingProject::class;

    public function definition(): array
    {
        $budgetHours = fake()->randomElement([40, 80, 120, 160, 200]);

        return [
            'tenant_id' => 1,
            'project_id' => Project::factory(),
            'tracking_code' => 'TS-'.strtoupper(fake()->bothify('??-####')),
            'description' => fake()->sentence(),
            'budget_hours' => $budgetHours,
            'hours_tracked' => fake()->numberBetween(0, (int) $budgetHours),
            'hours_remaining' => fake()->optional()->numberBetween(0, (int) $budgetHours),
            'budget_cost' => fake()->optional()->randomFloat(2, 5000, 50000),
            'status' => fake()->randomElement(['active', 'paused', 'completed']),
            'start_date' => fake()->dateTime('-90 days'),
            'end_date' => fake()->optional()->dateTime(),
            'assigned_employees' => [],
        ];
    }
}
