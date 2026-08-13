<?php

declare(strict_types=1);

namespace Modules\Timesheets\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Timesheets\Models\TimeAllocation;
use Modules\Timesheets\Models\TimesheetEntry;

/** @extends Factory<TimeAllocation> */
class TimeAllocationFactory extends Factory
{
    protected $model = TimeAllocation::class;

    public function definition(): array
    {
        $hoursAllocated = fake()->randomFloat(2, 1, 8);
        $hourlyRate = fake()->randomElement([25, 50, 75, 100, 125, 150]);

        return [
            'tenant_id' => 1,
            'timesheet_entry_id' => TimesheetEntry::factory(),
            'project_id' => null,
            'cost_center_id' => null,
            'task_id' => null,
            'hours_allocated' => $hoursAllocated,
            'allocation_type' => fake()->randomElement(['project', 'cost_center', 'task']),
            'hourly_rate' => $hourlyRate,
            'cost_amount' => $hoursAllocated * $hourlyRate,
            'description' => fake()->optional()->sentence(),
            'billable' => fake()->randomElement(['yes', 'no', 'partial']),
        ];
    }
}
