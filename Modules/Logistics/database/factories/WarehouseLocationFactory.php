<?php

namespace Modules\Logistics\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Logistics\app\Models\WarehouseLocation;

class WarehouseLocationFactory extends Factory
{
    protected $model = WarehouseLocation::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'zone_id' => fake()->word(),
            'warehouse_id' => fake()->word(),
            'code' => fake()->bothify('??-##'),
            'type' => fake()->word(),
            'max_weight_kg' => fake()->word(),
            'max_volume_m3' => fake()->word(),
            'is_occupied' => fake()->word(),
            'current_product_id' => fake()->word(),
            'current_lot_id' => fake()->word(),
            'name' => fake()->word(),
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