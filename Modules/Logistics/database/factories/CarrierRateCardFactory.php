<?php

namespace Modules\Logistics\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Logistics\Models\CarrierRateCard;

class CarrierRateCardFactory extends Factory
{
    protected $model = CarrierRateCard::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'carrier_id' => null,
            'origin_country' => fake()->countryCode(),
            'dest_country' => fake()->countryCode(),
            'service_type' => fake()->randomElement(['standard', 'express', 'economy']),
            'weight_min_kg' => 0,
            'weight_max_kg' => fake()->randomFloat(3, 10, 100),
            'base_rate' => fake()->randomFloat(2, 5, 50),
            'per_kg_rate' => fake()->randomFloat(4, 0.5, 5),
            'currency' => 'XOF',
            'transit_days_min' => fake()->numberBetween(1, 3),
            'transit_days_max' => fake()->numberBetween(4, 10),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
