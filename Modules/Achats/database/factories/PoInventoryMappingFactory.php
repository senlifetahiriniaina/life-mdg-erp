<?php

namespace Modules\Achats\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Achats\Models\PoInventoryMapping;
use Modules\Achats\Models\PurchaseOrder;
use Modules\Inventory\Models\Product;

class PoInventoryMappingFactory extends Factory
{
    protected $model = PoInventoryMapping::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'purchase_order_id' => PurchaseOrder::factory(),
            'product_id' => Product::factory(),
            'received_qty' => fake()->randomFloat(4, 1, 1000),
            'status' => 'pending',
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
