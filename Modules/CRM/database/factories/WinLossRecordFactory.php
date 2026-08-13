<?php

declare(strict_types=1);

namespace Modules\CRM\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\CRM\Models\WinLossRecord;

class WinLossRecordFactory extends Factory
{
    protected $model = WinLossRecord::class;

    public function definition(): array
    {
        return [
            'opportunity_id' => fake()->numberBetween(1, 50),
            'outcome' => fake()->randomElement(['won', 'lost']),
            'reason' => null,
            'competitor' => null,
            'deal_value' => fake()->randomFloat(2, 5000, 100000),
            'sales_cycle_days' => null,
            'recorded_by' => null,
            'recorded_at' => now(),
        ];
    }
}
