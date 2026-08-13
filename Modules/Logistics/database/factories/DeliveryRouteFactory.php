<?php

namespace Modules\Logistics\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Logistics\app\Models\DeliveryRoute;

class DeliveryRouteFactory extends Factory
{
    protected $model = DeliveryRoute::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'company_id' => fake()->word(),
            'reference' => fake()->bothify('??-##'),
            'name' => fake()->word(),
            'date' => fake()->word(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'vehicle_id' => fake()->word(),
            'driver_id' => fake()->word(),
            'total_distance_km' => fake()->word(),
            'total_duration_min' => fake()->word(),
            'total_stops' => fake()->word(),
            'optimized' => fake()->word(),
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
            'notes' => fake()->text(),
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