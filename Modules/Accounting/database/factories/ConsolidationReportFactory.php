<?php

declare(strict_types=1);

namespace Modules\Accounting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\Company;
use Modules\Accounting\Models\ConsolidationReport;

/** @extends Factory<ConsolidationReport> */
class ConsolidationReportFactory extends Factory
{
    protected $model = ConsolidationReport::class;

    public function definition(): array
    {
        $revenue = fake()->randomFloat(2, 100000, 1000000);
        $expenses = fake()->randomFloat(2, 50000, (int) $revenue);
        $assets = fake()->randomFloat(2, 200000, 2000000);
        $liabilities = fake()->randomFloat(2, 50000, (int) $assets);

        return [
            'parent_company_id' => Company::factory()->parent(),
            'report_date' => now()->toDateString(),
            'period_start' => now()->startOfYear()->toDateString(),
            'period_end' => now()->endOfYear()->toDateString(),
            'report_type' => 'full',
            'status' => 'draft',
            'included_companies' => [],
            'total_revenue' => $revenue,
            'total_expenses' => $expenses,
            'net_income' => $revenue - $expenses,
            'total_assets' => $assets,
            'total_liabilities' => $liabilities,
            'minority_interest' => 0,
            'eliminations_total' => 0,
            'report_data' => [],
            'generated_at' => now(),
        ];
    }
}
