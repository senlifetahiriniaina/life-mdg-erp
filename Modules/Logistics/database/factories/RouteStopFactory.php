<?php

namespace Modules\Logistics\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Logistics\Models\RouteStop;

class RouteStopFactory extends Factory
{
    protected $model = RouteStop::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'route_id' => null,
            'shipment_id' => null,
            'sequence' => fake()->numberBetween(1, 20),
            'type' => fake()->randomElement(['pickup', 'delivery', 'return']),
            'address' => fake()->address(),
            'lat' => fake()->latitude(),
            'lng' => fake()->longitude(),
            'planned_arrival' => fake()->time('H:i'),
            'actual_arrival' => null,
            'planned_duration_min' => fake()->numberBetween(5, 60),
            'status' => 'pending',
            'proof_of_delivery' => null,
            'signature_url' => null,
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'actual_arrival' => now(),
        ]);
    }
}
