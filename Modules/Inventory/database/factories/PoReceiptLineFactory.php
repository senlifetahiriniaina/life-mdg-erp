<?php

namespace Modules\Inventory\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\app\Models\PoReceiptLine;

class PoReceiptLineFactory extends Factory
{
    protected $model = PoReceiptLine::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'receipt_id' => fake()->word(),
            'product_id' => fake()->word(),
            'lot_number' => fake()->word(),
            'expiry_date' => fake()->word(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
        ];
    }

    /**
     * Indicate model is inactive
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
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