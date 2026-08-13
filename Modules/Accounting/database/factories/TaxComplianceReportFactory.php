<?php

namespace Modules\Accounting\Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\TaxComplianceReport;
use Modules\Accounting\Models\TaxJurisdiction;

class TaxComplianceReportFactory extends Factory
{
    protected $model = TaxComplianceReport::class;

    public function definition(): array
    {
        $periodStart = $this->faker->dateTimeBetween('-1 year', 'now');

        return [
            'company_id' => Company::factory(),
            'tax_jurisdiction_id' => TaxJurisdiction::factory(),
            'report_period_start' => $periodStart,
            'report_period_end' => $this->faker->dateTimeBetween($periodStart, '+3 months'),
            'status' => 'draft',
            'total_tax_liability' => $this->faker->numberBetween(5000, 100000),
            'total_tax_paid' => $this->faker->numberBetween(5000, 100000),
        ];
    }
}
