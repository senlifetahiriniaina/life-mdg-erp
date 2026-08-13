<?php

declare(strict_types=1);

namespace Modules\Logistics\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Logistics\Models\Shipment;
use Modules\Logistics\Models\ShipmentPackage;

/** @extends Factory<ShipmentPackage> */
class ShipmentPackageFactory extends Factory
{
    protected $model = ShipmentPackage::class;

    public function definition(): array
    {
        return [
            'shipment_id' => Shipment::factory(),
            'package_number' => fake()->unique()->bothify('PKG-####'),
            'type' => fake()->randomElement(['box', 'pallet', 'envelope', 'tube', 'crate']),
            'weight_kg' => fake()->randomFloat(3, 0.1, 100.0),
            'length_cm' => fake()->randomFloat(2, 10, 200),
            'width_cm' => fake()->randomFloat(2, 10, 150),
            'height_cm' => fake()->randomFloat(2, 5, 100),
            'tracking_number' => fake()->optional()->bothify('???#########'),
            'seal_number' => null,
            'is_fragile' => fake()->boolean(20),
        ];
    }

    public function fragile(): static
    {
        return $this->state(fn (array $attributes) => ['is_fragile' => true]);
    }
}
