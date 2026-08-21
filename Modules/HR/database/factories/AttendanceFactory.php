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
            'employee_id'     => Employee::factory(),
            'date'            => $this->faker->date(),
            // Chantier 32.17: real columns are check_in_time/check_out_time
            // (see Attendance model's own docblock) — 'check_in'/'check_out'
            // were never real columns, silently dropped by mass-assignment
            // guarding rather than erroring, which is exactly why this bug
            // went undetected until this chantier actually exercised the
            // real Attendance::create() path via tinker.
            'check_in_time'   => $this->faker->time('H:i:s'),
            'check_out_time'  => $this->faker->time('H:i:s'),
            'status'          => $this->faker->randomElement(['present', 'absent', 'late', 'half_day', 'on_leave']),
            'notes'           => $this->faker->sentence(),
        ];
    }
}
