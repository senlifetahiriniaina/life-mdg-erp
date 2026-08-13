<?php

namespace Modules\Logistics\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Logistics\app\Models\LgxCarrier;

class LgxCarrierFactory extends Factory
{
    protected $model = LgxCarrier::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'company_id' => fake()->word(),
            'name' => fake()->word(),
            'code' => fake()->bothify('??-##'),
            'type' => fake()->word(),
            'api_type' => fake()->word(),
            'api_endpoint' => fake()->word(),
            'tracking_url_pattern' => fake()->word(),
            'is_active' => true,
            'countries_served' => fake()->word(),
            'title' => fake()->word(),
            'description' => fake()->text(),
            'slug' => fake()->slug(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'amount' => fake()->randomFloat(2, 0, 1000),
            'quantity' => fake()->numberBetween(1, 100),
            'price' => fake()->randomFloat(2, 0, 1000),
            'cost' => fake()->randomFloat(2, 0, 1000),
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