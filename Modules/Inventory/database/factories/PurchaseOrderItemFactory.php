<?php

namespace Modules\Inventory\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\app\Models\PurchaseOrderItem;

class PurchaseOrderItemFactory extends Factory
{
    protected $model = PurchaseOrderItem::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'purchase_order_id' => fake()->word(),
            'product_id' => fake()->word(),
            'product_name' => fake()->word(),
            'sku' => fake()->word(),
            'quantity_ordered' => fake()->word(),
            'quantity_received' => fake()->word(),
            'unit_price' => fake()->word(),
            'tax_rate' => fake()->word(),
            'total_price' => fake()->word(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'quantity' => fake()->numberBetween(1, 100),
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