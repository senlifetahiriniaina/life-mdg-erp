<?php

namespace Modules\HR\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\HR\Models\Employee;
use Modules\HR\Models\EmployeeCompensation;

class EmployeeCompensationFactory extends Factory
{
    protected $model = EmployeeCompensation::class;

    public function definition(): array
    {
        $baseSalary = fake()->randomFloat(2, 300000, 3000000);

        return [
            'employee_id' => fn () => Employee::factory()->create()->id,
            'base_salary' => $baseSalary,
            'currency' => 'MGA',
            'bonus_amount' => fake()->randomFloat(2, 0, $baseSalary * 0.2),
            'bonus_frequency' => fake()->randomElement(['annual', 'semi-annual', 'quarterly']),
            'equity_granted' => 0,
            'equity_vesting_period_months' => null,
            'equity_vesting_schedule' => null,
            'equity_vested_percentage' => null,
            'benefits_annual_value' => fake()->randomFloat(2, 0, 500000),
            'total_compensation' => null,
            'effective_date' => fake()->dateTimeBetween('-1 year', 'now'),
            'end_date' => null,
            'notes' => null,
        ];
    }
}
