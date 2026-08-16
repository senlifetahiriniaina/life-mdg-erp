<?php

declare(strict_types=1);

namespace Modules\Payroll\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Payroll\Models\PayrollRun;

class PayrollRunFactory extends Factory
{
    protected $model = PayrollRun::class;

    public function definition(): array
    {
        return [
            'tenant_id' => 1,
            // payroll_runs has a unique(tenant_id, period) constraint -- vary the month
            // per call so tests that create several PayrollRuns via nested Payslip
            // factories (payroll_run_id => PayrollRunFactory::new()) don't collide.
            'period' => now()->subMonths($this->faker->unique()->numberBetween(0, 3650))->startOfMonth()->toDateString(),
            'status' => 'paid',
            'currency' => 'MGA',
            'total_gross' => 0,
            'total_deductions' => 0,
            'total_net' => 0,
        ];
    }
}
