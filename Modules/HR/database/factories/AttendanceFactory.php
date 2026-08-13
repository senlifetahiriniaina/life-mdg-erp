<?php

namespace Modules\HR\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\HR\Models\Attendance;
use Modules\HR\Models\Employee;

class AttendanceFactory extends Factory
{
    protected $model = Attendance::class;

    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'date'        => $this->faker->date(),
            'check_in'    => $this->faker->time('H:i:s'),
            'check_out'   => $this->faker->time('H:i:s'),
            'status'      => $this->faker->randomElement(['present', 'absent', 'late', 'half_day', 'on_leave']),
            'notes'       => $this->faker->sentence(),
        ];
    }
}
