<?php

namespace Modules\Logistics\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Logistics\Models\WarehouseMovement;

class WarehouseMovementFactory extends Factory
{
    protected $model = WarehouseMovement::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'company_id' => fake()->word(),
            'warehouse_id' => fake()->word(),
            'from_location_id' => fake()->word(),
            'to_location_id' => fake()->word(),
            'product_id' => fake()->word(),
            'lot_number' => fake()->word(),
            'serial_number' => fake()->word(),
            'qty' => fake()->word(),
            'unit' => fake()->word(),
            'type' => fake()->word(),
            'reference' => fake()->bothify('??-##'),
            'reference_type' => fake()->word(),
            'operator_id' => fake()->word(),
            'notes' => fake()->text(),
            'name' => fake()->word(),
            'title' => fake()->word(),
            'description' => fake()->text(),
            'slug' => fake()->slug(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
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