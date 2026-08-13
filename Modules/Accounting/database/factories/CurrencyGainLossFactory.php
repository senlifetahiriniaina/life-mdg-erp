<?php

declare(strict_types=1);

namespace Modules\Accounting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\CurrencyGainLoss;

/** @extends Factory<CurrencyGainLoss> */
class CurrencyGainLossFactory extends Factory
{
    protected $model = CurrencyGainLoss::class;

    public function definition(): array
    {
        $original = fake()->randomFloat(2, 100, 10000);
        $converted = $original * fake()->randomFloat(6, 0.8, 1.2);
        $gainLoss = $converted - $original;

        return [
            'invoice_id' => null,
            'original_amount' => $original,
            'original_currency' => fake()->randomElement(['USD', 'GBP', 'CHF']),
            'converted_amount' => $converted,
            'base_currency' => 'EUR',
            'gain_loss' => $gainLoss,
            'realized' => fake()->boolean(),
        ];
    }
}
