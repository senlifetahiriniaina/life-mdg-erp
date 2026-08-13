<?php

declare(strict_types=1);

namespace Modules\Inventory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Models\Shipment;
use Modules\Inventory\Models\ShipmentEvent;

/** @extends Factory<ShipmentEvent> */
class ShipmentEventFactory extends Factory
{
    protected $model = ShipmentEvent::class;

    public function definition(): array
    {
        $statuses = ['booked', 'picked_up', 'in_transit', 'out_for_delivery', 'delivered'];
        $locations = ['Paris', 'Lyon', 'Marseille', 'Bordeaux', 'Lille', 'Nantes', 'Toulouse'];

        return [
            'shipment_id' => Shipment::factory(),
            'status' => fake()->randomElement($statuses),
            'location' => fake()->randomElement($locations),
            'description' => fake()->sentence(),
            'occurred_at' => fake()->dateTimeBetween('-7 days', 'now'),
        ];
    }
}
