<?php

namespace Modules\HR\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\HR\Models\AttendanceRecord;
use Modules\HR\Models\Employee;

class AttendanceRecordFactory extends Factory
{
    protected $model = AttendanceRecord::class;

    public function definition(): array
    {
        $clockIn = fake()->dateTimeBetween('-30 days', 'now');

        return [
            'employee_id' => fn () => Employee::factory()->create()->id,
            'clock_in' => $clockIn,
            'clock_out' => null,
            'break_minutes' => 0,
            'type' => fake()->randomElement(['regular', 'overtime', 'remote']),
            'notes' => null,
            'ip_address' => fake()->ipv4(),
            'location_lat' => null,
            'location_lng' => null,
        ];
    }
}
