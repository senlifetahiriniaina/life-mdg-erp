<?php

declare(strict_types=1);

namespace Modules\Timesheets\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\HR\Models\Employee;
use Modules\Timesheets\Models\TimesheetEntry;

/** @extends Factory<TimesheetEntry> */
class TimesheetEntryFactory extends Factory
{
    protected $model = TimesheetEntry::class;

    public function definition(): array
    {
        return [
            'tenant_id' => 1,
            'employee_id' => Employee::factory(),
            'entry_date' => fake()->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'hours_worked' => fake()->randomElement([4, 6, 8, 9, 10]),
            'status' => fake()->randomElement(['draft', 'submitted', 'approved']),
            'project_id' => null,
            'task_id' => null,
            'description' => fake()->sentence(),
            'notes' => fake()->optional()->sentence(),
            'submitted_by' => null,
            'submitted_at' => fake()->optional()->dateTime(),
            'approved_by' => null,
            'approved_at' => fake()->optional()->dateTime(),
        ];
    }
}
