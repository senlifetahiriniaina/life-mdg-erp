<?php

namespace Modules\HR\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\HR\Models\Department;

class DepartmentFactory extends Factory
{
    protected $model = Department::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->words(2, true),
            'code' => strtoupper($this->faker->unique()->lexify('DEPT??')),
            'description' => $this->faker->sentence(),
            'status' => 'active',
        ];
    }

    public function withBudget(int $min = 100000, int $max = 5000000): static
    {
        return $this->state([
            'budget_allocation' => $this->faker->numberBetween($min, $max),
        ]);
    }
}
