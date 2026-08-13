<?php

namespace Modules\HR\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\HR\Models\BiometricDevice;

class BiometricDeviceFactory extends Factory
{
    protected $model = BiometricDevice::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'device_id' => fake()->word(),
            'device_name' => fake()->word(),
            'device_type' => fake()->word(),
            'manufacturer' => fake()->word(),
            'model' => fake()->word(),
            'location' => fake()->word(),
            'building' => fake()->word(),
            'floor' => fake()->word(),
            'zone' => fake()->word(),
            'latitude' => fake()->word(),
            'longitude' => fake()->word(),
            'ip_address' => fake()->word(),
            'mac_address' => fake()->word(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'last_sync' => fake()->word(),
            'firmware_version' => fake()->word(),
            'capacity' => fake()->word(),
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