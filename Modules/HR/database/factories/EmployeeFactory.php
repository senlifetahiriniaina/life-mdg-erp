<?php

namespace Modules\HR\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\HR\Models\Department;
use Modules\HR\Models\Employee;
use Modules\HR\Models\JobPosition;

class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    public function definition(): array
    {
        return [
            'employee_number' => 'EMP'.str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT),
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'email' => $this->faker->unique()->email(),
            'phone' => $this->faker->phoneNumber(),
            'date_of_birth' => $this->faker->dateTimeBetween('-50 years', '-18 years'),
            'gender' => $this->faker->randomElement(['male', 'female', 'other']),
            'nationality' => $this->faker->country(),
            'address' => $this->faker->address(),
            'department_id' => fn () => Department::factory()->create()->id,
            'job_position_id' => fn () => JobPosition::factory()->create()->id,
            'user_id' => fn () => User::factory()->create()->id,
            'hire_date' => $this->faker->dateTimeBetween('-5 years', 'now'),
            'employment_type' => $this->faker->randomElement(['full_time', 'part_time', 'contract']),
            'status' => 'probation',
        ];
    }

    public function terminated(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'terminated',
            'termination_date' => now()->subDays(random_int(1, 90))->toDateString(),
            'termination_reason' => 'Resignation',
        ]);
    }
}
