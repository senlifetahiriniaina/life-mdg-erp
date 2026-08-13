<?php

namespace Modules\Accounting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\DepreciationSchedule;
use Modules\Accounting\Models\FixedAsset;
use Modules\Accounting\Models\GlAccount;

class DepreciationScheduleFactory extends Factory
{
    protected $model = DepreciationSchedule::class;

    public function definition(): array
    {
        return [
            'fixed_asset_id' => FixedAsset::factory(),
            'depreciation_method' => 'straight_line',
            'useful_life_years' => 10,
            'residual_value' => 5000,
            'depreciation_start_date' => now()->subYears(2)->toDateString(),
            'depreciation_end_date' => null,
            'annual_depreciation_amount' => 9500,
            'accumulated_depreciation' => 19000,
            'book_value' => 81000,
            'depreciation_expense_account_id' => GlAccount::factory(),
            'accumulated_depreciation_account_id' => GlAccount::factory(),
            'status' => 'active',
        ];
    }
}
