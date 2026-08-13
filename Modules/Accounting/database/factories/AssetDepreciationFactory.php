<?php

declare(strict_types=1);

namespace Modules\Accounting\Database\Factories;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\AssetDepreciation;
use Modules\Accounting\Models\FixedAsset;

/** @extends Factory<AssetDepreciation> */
class AssetDepreciationFactory extends Factory
{
    protected $model = AssetDepreciation::class;

    public function definition(): array
    {
        $month = fake()->numberBetween(1, 12);
        $year = fake()->randomElement([2025, 2026]);

        $periodStart = Carbon::create($year, $month, 1)->startOfMonth()->format('Y-m-d');
        $periodEnd = Carbon::create($year, $month, 1)->endOfMonth()->format('Y-m-d');

        return [
            'asset_id' => FixedAsset::factory(),
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'period_month' => $month,
            'period_year' => $year,
            'depreciation_amount' => fake()->randomFloat(2, 500, 5000),
            'accumulated_depreciation' => fake()->randomFloat(2, 1000, 50000),
            'net_book_value' => fake()->randomFloat(2, 10000, 200000),
            'units_used' => null,
            'journal_entry_id' => null,
        ];
    }
}
