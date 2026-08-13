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
                        'route_id' => fake()->word(),
            'shipment_id' => fake()->word(),
            'sequence' => fake()->word(),
            'type' => fake()->word(),
            'address' => fake()->word(),
            'lat' => fake()->word(),
            'lng' => fake()->word(),
            'planned_arrival' => fake()->word(),
            'actual_arrival' => fake()->word(),
            'planned_duration_min' => fake()->word(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'proof_of_delivery' => fake()->word(),
            'signature_url' => fake()->word(),
            'notes' => fake()->text(),
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