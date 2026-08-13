<?php

namespace Modules\Logistics\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Logistics\Models\LgxTrackingEvent;

class LgxTrackingEventFactory extends Factory
{
    protected $model = LgxTrackingEvent::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'shipment_id' => fake()->word(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'location' => fake()->word(),
            'lat' => fake()->word(),
            'lng' => fake()->word(),
            'description' => fake()->text(),
            'carrier_event_code' => fake()->word(),
            'occurred_at' => fake()->word(),
            'name' => fake()->word(),
            'title' => fake()->word(),
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