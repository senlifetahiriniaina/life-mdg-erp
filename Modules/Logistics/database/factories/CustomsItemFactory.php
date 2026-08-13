<?php

namespace Modules\Logistics\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Logistics\app\Models\CustomsItem;

class CustomsItemFactory extends Factory
{
    protected $model = CustomsItem::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'declaration_id' => fake()->word(),
            'product_id' => fake()->word(),
            'description' => fake()->text(),
            'hs_code' => fake()->word(),
            'qty' => fake()->word(),
            'unit' => fake()->word(),
            'unit_value' => fake()->word(),
            'total_value' => fake()->word(),
            'country_of_origin' => fake()->word(),
            'weight_kg' => fake()->word(),
            'name' => fake()->word(),
            'title' => fake()->word(),
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