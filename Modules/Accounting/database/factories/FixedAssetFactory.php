<?php

declare(strict_types=1);

namespace Modules\Accounting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\FixedAsset;

/** @extends Factory<FixedAsset> */
class FixedAssetFactory extends Factory
{
    protected $model = FixedAsset::class;

    public function definition(): array
    {
        $acquisitionCost = fake()->randomFloat(2, 5000, 500000);
        $salvageValue = round($acquisitionCost * 0.10, 2);
        $depreciationMethod = fake()->randomElement(['straight-line', 'declining-balance', 'units-of-production']);

        return [
            'tenant_id' => 1,
            'asset_code' => 'FA-'.strtoupper(fake()->bothify('???###')),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'asset_class' => fake()->randomElement(['building', 'machinery', 'vehicle', 'equipment', 'furniture', 'intangible', 'other']),
            'acquisition_date' => fake()->dateTimeBetween('-5 years', '-1 year')->format('Y-m-d'),
            'acquisition_cost' => $acquisitionCost,
            'salvage_value' => $salvageValue,
            'useful_life_years' => fake()->randomElement([3, 5, 7, 10, 15, 20]),
            'depreciation_method' => $depreciationMethod,
            'asset_account_id' => 1,
            'depreciation_expense_account_id' => 1,
            'accumulated_depreciation_account_id' => 1,
            'status' => 'active',
        ];
    }
}
