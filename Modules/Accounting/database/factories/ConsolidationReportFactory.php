<?php

declare(strict_types=1);

namespace Modules\Accounting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\ConsolidationGroup;
use Modules\Accounting\Models\ConsolidationReport;

/** @extends Factory<ConsolidationReport> */
class ConsolidationReportFactory extends Factory
{
    protected $model = ConsolidationReport::class;

    public function definition(): array
    {
        return [
            'consolidation_group_id' => ConsolidationGroup::factory(),
            'report_type' => 'consolidated_balance_sheet',
            'reporting_currency' => 'XOF',
            'consolidated_data' => [],
            'intercompany_eliminations' => [],
            'exchange_differences' => [],
            'total_adjustments' => 0,
            'status' => 'draft',
            'auditor_notes' => null,
            'created_by' => null,
            'finalized_at' => null,
        ];
    }
}
