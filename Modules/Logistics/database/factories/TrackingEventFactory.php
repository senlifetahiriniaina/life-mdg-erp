<?php

declare(strict_types=1);

namespace Modules\Logistics\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Logistics\Models\Shipment;
use Modules\Logistics\Models\TrackingEvent;

/** @extends Factory<TrackingEvent> */
class TrackingEventFactory extends Factory
{
    protected $model = TrackingEvent::class;

    public function definition(): array
    {
        return [
            'shipment_id' => Shipment::factory(),
            'event_type' => fake()->randomElement([
                'booked', 'picked_up', 'in_transit', 'customs_clearance',
                'out_for_delivery', 'delivered', 'exception',
            ]),
            'status_detail' => fake()->optional()->sentence(),
            'location_name' => fake()->optional()->city().' Hub',
            'location_city' => fake()->city(),
            'location_country' => strtoupper(fake()->countryCode()),
            'latitude' => fake()->optional()->latitude(),
            'longitude' => fake()->optional()->longitude(),
            'carrier_ref' => fake()->optional()->bothify('EVT-########'),
            'is_exception' => false,
            'exception_reason' => null,
            'recorded_at' => fake()->dateTimeBetween('-30 days', 'now'),
            'recorded_by' => null,
        ];
    }

    public function exception(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => 'exception',
            'is_exception' => true,
            'exception_reason' => fake()->randomElement([
                'Address not found',
                'Recipient unavailable',
                'Customs hold',
                'Weather delay',
            ]),
        ]);
    }
}
