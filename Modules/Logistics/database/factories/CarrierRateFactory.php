<?php

declare(strict_types=1);

namespace Modules\Logistics\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Logistics\Models\Carrier;
use Modules\Logistics\Models\CarrierRate;

/** @extends Factory<CarrierRate> */
class CarrierRateFactory extends Factory
{
    protected $model = CarrierRate::class;

    public function definition(): array
    {
        return [
            'carrier_id' => Carrier::factory(),
            'name' => fake()->words(3, true).' Rate',
            'mode' => fake()->randomElement(['road', 'air', 'sea', 'rail']),
            'origin_country' => fake()->countryCode(),
            'destination_country' => fake()->countryCode(),
            'rate_type' => fake()->randomElement(['flat', 'per_kg', 'per_km', 'per_piece']),
            'base_rate' => fake()->randomFloat(2, 50, 2000),
            'fuel_surcharge_pct' => fake()->randomFloat(2, 0, 25),
            'insurance_rate_pct' => fake()->randomFloat(2, 0, 5),
            'min_charge' => fake()->randomFloat(2, 10, 100),
            'currency' => 'USD',
            'transit_days' => fake()->numberBetween(1, 30),
            'is_active' => true,
        ];
    }
}
