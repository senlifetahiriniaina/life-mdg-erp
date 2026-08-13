<?php

declare(strict_types=1);

namespace Modules\Accounting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\AssetDisposal;
use Modules\Accounting\Models\FixedAsset;

/** @extends Factory<AssetDisposal> */
class AssetDisposalFactory extends Factory
{
    protected $model = AssetDisposal::class;

    public function definition(): array
    {
        $disposalProceeds = fake()->randomFloat(2, 5000, 100000);
        $netBookValueAtDisposal = fake()->randomFloat(2, 8000, 120000);
        $gainLoss = round($disposalProceeds - $netBookValueAtDisposal, 2);

        return [
            'asset_id' => FixedAsset::factory(),
            'disposal_date' => fake()->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
            'disposal_type' => fake()->randomElement(['sale', 'write_off', 'trade_in']),
            'disposal_proceeds' => $disposalProceeds,
            'net_book_value_at_disposal' => $netBookValueAtDisposal,
            'gain_loss' => $gainLoss,
            'notes' => fake()->optional()->sentence(),
            'journal_entry_id' => null,
            'created_by' => 1,
        ];
    }
}
