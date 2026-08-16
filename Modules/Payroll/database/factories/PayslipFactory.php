<?php

declare(strict_types=1);

namespace Modules\Payroll\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\HR\Models\Employee;
use Modules\Payroll\Models\Payslip;

class PayslipFactory extends Factory
{
    protected $model = Payslip::class;

    public function definition(): array
    {
        return [
            'payroll_run_id' => PayrollRunFactory::new(),
            'tenant_id' => 1,
            'employee_id' => Employee::factory(),
            'employee_name' => $this->faker->name(),
            'period' => now()->startOfMonth()->toDateString(),
            'gross_salary' => 500000,
            'total_deductions' => 50000,
            'net_salary' => 450000,
            'currency' => 'MGA',
            'status' => 'paid',
        ];
    }
}
