<?php

namespace Modules\Logistics\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Logistics\Models\Route;

class RouteFactory extends Factory
{
    protected $model = Route::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'name' => fake()->word(),
            'carrier_id' => fake()->word(),
            'is_active' => true,
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'cost' => fake()->randomFloat(2, 0, 1000),
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
        ]);
    }
}