<?php

namespace Modules\Logistics\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Logistics\Models\DeliveryRoute;

class DeliveryRouteFactory extends Factory
{
    protected $model = DeliveryRoute::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'company_id' => 1,
            'reference' => fake()->bothify('RTE-####-##'),
            'name' => fake()->city().' Delivery Run',
            'date' => fake()->dateTimeBetween('now', '+1 week')->format('Y-m-d'),
            'status' => 'planned',
            'vehicle_id' => null,
            'driver_id' => null,
            'total_distance_km' => fake()->randomFloat(2, 5, 300),
            'total_duration_min' => fake()->numberBetween(20, 480),
            'total_stops' => fake()->numberBetween(1, 15),
            'optimized' => false,
        ];
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'in_progress']);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'completed']);
    }
}
