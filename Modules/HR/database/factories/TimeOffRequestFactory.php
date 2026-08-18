<?php

namespace Modules\HR\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\HR\Models\Employee;
use Modules\HR\Models\TimeOffRequest;

class TimeOffRequestFactory extends Factory
{
    protected $model = TimeOffRequest::class;

    public function definition(): array
    {
        $start = fake()->dateTimeBetween('now', '+30 days');
        $end = (clone $start)->modify('+'.fake()->numberBetween(1, 5).' days');

        return [
            'employee_id' => fn () => Employee::factory()->create()->id,
            'request_type' => fake()->randomElement(['pto', 'sick', 'unpaid', 'sabbatical', 'personal', 'jury_duty']),
            'start_date' => $start,
            'end_date' => $end,
            'duration_days' => fake()->numberBetween(1, 5),
            'duration_hours' => null,
            'status' => 'pending',
            'approved_by' => null,
            'reason' => fake()->sentence(),
            'rejection_reason' => null,
            'approved_at' => null,
            'rejected_at' => null,
            'partial_day' => false,
            'partial_day_details' => null,
            'is_urgent' => fake()->boolean(20),
        ];
    }
}
