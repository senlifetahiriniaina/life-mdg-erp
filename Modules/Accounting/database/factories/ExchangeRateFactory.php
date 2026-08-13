<?php

declare(strict_types=1);

namespace Modules\Accounting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\ExchangeRate;

/** @extends Factory<ExchangeRate> */
class ExchangeRateFactory extends Factory
{
    protected $model = ExchangeRate::class;

    public function definition(): array
    {
        $currencies = ['EUR', 'USD', 'GBP', 'CHF', 'JPY'];

        return [
            'base_currency' => 'EUR',
            'target_currency' => fake()->randomElement(['USD', 'GBP', 'CHF', 'JPY']),
            'rate' => fake()->randomFloat(6, 0.5, 2.0),
            'source' => fake()->randomElement(['manual', 'api']),
            'date' => fake()->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
        ];
    }
}
