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
                        'carrier_id' => fake()->word(),
            'origin_country' => fake()->word(),
            'dest_country' => fake()->word(),
            'service_type' => fake()->word(),
            'weight_min_kg' => fake()->word(),
            'weight_max_kg' => fake()->word(),
            'base_rate' => fake()->word(),
            'per_kg_rate' => fake()->word(),
            'currency' => fake()->word(),
            'transit_days_min' => fake()->word(),
            'transit_days_max' => fake()->word(),
            'is_active' => true,
            'name' => fake()->word(),
            'title' => fake()->word(),
            'description' => fake()->text(),
            'slug' => fake()->slug(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'code' => fake()->bothify('??-##'),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'amount' => fake()->randomFloat(2, 0, 1000),
            'quantity' => fake()->numberBetween(1, 100),
            'price' => fake()->randomFloat(2, 0, 1000),
            'cost' => fake()->randomFloat(2, 0, 1000),
            'notes' => fake()->text(),
        ];
    }

    /**
     * Indicate model is inactive
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate model is archived
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'archived_at' => now(),
        ]);
    }
}