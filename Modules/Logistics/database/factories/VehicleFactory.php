<?php

namespace Modules\Logistics\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Logistics\Models\Vehicle;

class VehicleFactory extends Factory
{
    protected $model = Vehicle::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'company_id' => fake()->word(),
            'name' => fake()->word(),
            'plate_number' => fake()->word(),
            'type' => fake()->word(),
            'max_weight_kg' => fake()->word(),
            'max_volume_m3' => fake()->word(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'driver_id' => fake()->word(),
            'fuel_type' => fake()->word(),
            'fuel_consumption_per_100km' => fake()->word(),
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