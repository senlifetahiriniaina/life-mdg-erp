<?php

namespace Modules\HR\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\HR\Models\AttendanceException;
use Modules\HR\Models\Employee;

class AttendanceExceptionFactory extends Factory
{
    protected $model = AttendanceException::class;

    public function definition(): array
    {
        return [
            'employee_id' => fn () => Employee::factory()->create()->id,
            'attendance_date' => fake()->dateTimeBetween('-30 days', 'now'),
            'exception_type' => fake()->randomElement(['late_arrival', 'early_departure', 'missed_clock_out', 'unscheduled_absence']),
            'minutes_late' => fake()->numberBetween(0, 60),
            'minutes_early' => fake()->numberBetween(0, 60),
            'reason' => fake()->sentence(),
            'status' => 'flagged',
            'approved_by' => null,
            'manager_notes' => null,
            'employee_response' => null,
            'acknowledged_at' => null,
            'approved_at' => null,
        ];
    }
}
