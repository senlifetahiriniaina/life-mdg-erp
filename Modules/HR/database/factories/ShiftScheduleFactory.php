<?php

namespace Modules\HR\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\HR\Models\Employee;
use Modules\HR\Models\ShiftSchedule;

class ShiftScheduleFactory extends Factory
{
    protected $model = ShiftSchedule::class;

    public function definition(): array
    {
        return [
            'employee_id' => fn () => Employee::factory()->create()->id,
            'shift_name' => fake()->randomElement(['Morning', 'Afternoon', 'Night', 'Split']),
            'shift_code' => fake()->bothify('SH-##'),
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'working_hours' => 8,
            'days_of_week' => [1, 2, 3, 4, 5],
            'is_night_shift' => false,
            'is_flexible' => fake()->boolean(20),
            'effective_from' => fake()->dateTimeBetween('-1 month', 'now'),
            'effective_to' => null,
            'status' => 'active',
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
