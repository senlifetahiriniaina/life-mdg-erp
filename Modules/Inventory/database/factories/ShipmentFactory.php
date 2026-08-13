<?php

declare(strict_types=1);

namespace Modules\Inventory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Models\Carrier;
use Modules\Inventory\Models\Shipment;

/** @extends Factory<Shipment> */
class ShipmentFactory extends Factory
{
    protected $model = Shipment::class;

    public function definition(): array
    {
        return [
            'carrier_id' => Carrier::factory(),
            'reference' => 'SHP-'.strtoupper(fake()->unique()->bothify('??????')),
            'order_id' => fake()->optional()->numberBetween(1, 1000),
            'status' => fake()->randomElement(['draft', 'booked', 'picked_up', 'in_transit', 'delivered']),
            'tracking_number' => fake()->optional()->bothify('???#########'),
            'label_url' => fake()->optional()->url(),
            'origin_address' => [
                'name' => fake()->company(),
                'street' => fake()->streetAddress(),
                'city' => fake()->city(),
                'zip' => fake()->postcode(),
                'country' => 'FR',
            ],
            'destination_address' => [
                'name' => fake()->name(),
                'street' => fake()->streetAddress(),
                'city' => fake()->city(),
                'zip' => fake()->postcode(),
                'country' => fake()->randomElement(['FR', 'DE', 'ES', 'IT', 'BE']),
            ],
            'weight_kg' => fake()->randomFloat(3, 0.1, 50),
            'dimensions' => [
                'length' => fake()->numberBetween(10, 100),
                'width' => fake()->numberBetween(10, 80),
                'height' => fake()->numberBetween(5, 60),
            ],
            'service_type' => fake()->randomElement(['express', 'standard', 'economy']),
            'estimated_cost' => fake()->randomFloat(2, 5, 200),
            'actual_cost' => fake()->optional()->randomFloat(2, 5, 200),
            'shipped_at' => fake()->optional()->dateTimeBetween('-30 days', 'now'),
            'estimated_delivery_at' => fake()->optional()->dateTimeBetween('now', '+7 days'),
            'delivered_at' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
            'tracking_number' => null,
            'shipped_at' => null,
            'delivered_at' => null,
        ]);
    }

    public function inTransit(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'in_transit',
            'tracking_number' => strtoupper(fake()->bothify('???#########')),
            'shipped_at' => now()->subDays(2),
            'delivered_at' => null,
        ]);
    }

    public function delivered(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'delivered',
            'delivered_at' => now()->subDay(),
        ]);
    }
}
