<?php

declare(strict_types=1);

namespace Modules\Logistics\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Logistics\Models\DeliveryRound;
use Modules\Logistics\Models\DeliveryStop;

/** @extends Factory<DeliveryStop> */
class DeliveryStopFactory extends Factory
{
    protected $model = DeliveryStop::class;

    public function definition(): array
    {
        return [
            'delivery_round_id' => DeliveryRound::factory(),
            'stop_order' => fake()->numberBetween(1, 20),
            'recipient_name' => fake()->company(),
            'recipient_address' => fake()->streetAddress(),
            'recipient_city' => fake()->city(),
            'recipient_country' => fake()->countryCode(),
            'recipient_phone' => fake()->phoneNumber(),
            'status' => 'pending',
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
