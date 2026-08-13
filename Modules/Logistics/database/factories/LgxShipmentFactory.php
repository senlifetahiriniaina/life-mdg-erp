<?php

namespace Modules\Logistics\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Logistics\app\Models\LgxShipment;

class LgxShipmentFactory extends Factory
{
    protected $model = LgxShipment::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'company_id' => fake()->word(),
            'reference' => fake()->bothify('??-##'),
            'type' => fake()->word(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'carrier_id' => fake()->word(),
            'carrier_service' => fake()->word(),
            'origin_warehouse_id' => fake()->word(),
            'origin_address' => fake()->word(),
            'dest_warehouse_id' => fake()->word(),
            'dest_address' => fake()->word(),
            'incoterm' => fake()->word(),
            'weight_kg' => fake()->word(),
            'volume_m3' => fake()->word(),
            'declared_value' => fake()->word(),
            'currency' => fake()->word(),
            'tracking_number' => fake()->word(),
            'estimated_delivery' => fake()->word(),
            'actual_delivery' => fake()->word(),
            'proof_of_delivery' => fake()->word(),
            'pod_signed_by' => fake()->word(),
            'notes' => fake()->text(),
            'created_by' => fake()->word(),
            'name' => fake()->word(),
            'title' => fake()->word(),
            'description' => fake()->text(),
            'slug' => fake()->slug(),
            'code' => fake()->bothify('??-##'),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'amount' => fake()->randomFloat(2, 0, 1000),
            'quantity' => fake()->numberBetween(1, 100),
            'price' => fake()->randomFloat(2, 0, 1000),
            'cost' => fake()->randomFloat(2, 0, 1000),
            'is_active' => true,
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