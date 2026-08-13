<?php

namespace Modules\HR\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\HR\Models\JobPosition;

class JobPositionFactory extends Factory
{
    protected $model = JobPosition::class;

    public function definition(): array
    {
        return [
            'department_id' => DepartmentFactory::new(),
            'title' => $this->faker->jobTitle(),
            'level' => $this->faker->randomElement(['junior', 'mid', 'senior', 'lead']),
            'is_active' => true,
        ];
    }
}
