<?php

namespace Modules\Inventory\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\app\Models\PoReceipt;

class PoReceiptFactory extends Factory
{
    protected $model = PoReceipt::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'purchase_order_id' => fake()->word(),
            'received_by' => fake()->word(),
            'received_at' => fake()->word(),
            'notes' => fake()->text(),
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