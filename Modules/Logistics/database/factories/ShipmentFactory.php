<?php

declare(strict_types=1);

namespace Modules\Logistics\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Logistics\Models\Shipment;

/** @extends Factory<Shipment> */
class ShipmentFactory extends Factory
{
    protected $model = Shipment::class;

    public function definition(): array
    {
        $date = now()->format('Ymd');

        return [
            'reference' => 'SHP-'.$date.'-'.fake()->unique()->numerify('####'),
            'type' => fake()->randomElement(['outbound', 'inbound', 'transfer', 'return']),
            'status' => 'draft',
            'shipper_name' => fake()->company(),
            'shipper_address' => fake()->streetAddress(),
            'shipper_city' => fake()->city(),
            'shipper_country' => fake()->countryCode(),
            'consignee_name' => fake()->company(),
            'consignee_address' => fake()->streetAddress(),
            'consignee_city' => fake()->city(),
            'consignee_country' => fake()->countryCode(),
            'transport_mode' => fake()->randomElement(['road', 'air', 'sea', 'rail']),
            'weight_kg' => fake()->randomFloat(3, 0.5, 5000),
            'incoterm' => fake()->randomElement(['EXW', 'FOB', 'CIF', 'DDP', 'DAP']),
            'requires_cold_chain' => false,
            'has_hazmat' => false,
        ];
    }

    public function booked(): static
    {
        return $this->state(['status' => 'booked', 'booked_at' => now()]);
    }

    public function inTransit(): static
    {
        return $this->state(['status' => 'in_transit', 'booked_at' => now()->subDay(), 'picked_up_at' => now()]);
    }

    public function delivered(): static
    {
        return $this->state([
            'status' => 'delivered',
            'booked_at' => now()->subDays(5),
            'picked_up_at' => now()->subDays(4),
            'delivered_at' => now(),
        ]);
    }
}
