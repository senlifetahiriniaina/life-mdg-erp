<?php

declare(strict_types=1);

namespace Modules\Accounting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\VatRate;

class VatRateFactory extends Factory
{
    protected $model = VatRate::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->randomElement(['Standard', 'Reduced', 'Zero', 'Exempt']),
            'rate' => $this->faker->randomElement([0.0, 0.05, 0.10, 0.18, 0.20]),
            'country_code' => $this->faker->countryCode(),
            'applies_from' => now()->subYear(),
            'applies_to' => null,
            'type' => $this->faker->randomElement(['standard', 'reduced', 'zero', 'exempt']),
            'is_default' => false,
        ];
    }
}
