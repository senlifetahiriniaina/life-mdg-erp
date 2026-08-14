<?php

namespace Modules\HR\Database\Factories;

use Modules\HR\Models\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;

class LeaveTypeFactory extends Factory
{
    protected $model = LeaveType::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->words(2, true),
            'code' => $this->faker->unique()->lexify('LT-????'),
            'days_per_year' => $this->faker->randomElement([0, 5, 10, 15, 20, 25, 30]),
            'is_paid' => $this->faker->boolean(80),
            'description' => $this->faker->sentence(),
            'status' => 'active',
        ];
    }
}
