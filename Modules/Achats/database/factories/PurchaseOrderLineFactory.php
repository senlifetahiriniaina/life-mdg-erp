<?php

namespace Modules\Achats\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Achats\Models\PurchaseOrder;
use Modules\Achats\Models\PurchaseOrderLine;

class PurchaseOrderLineFactory extends Factory
{
    protected $model = PurchaseOrderLine::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'purchase_order_id' => PurchaseOrder::factory(),
            'description' => fake()->text(),
            'quantity' => fake()->randomFloat(4, 1, 100),
            'unit' => fake()->randomElement(['pcs', 'kg', 'box', 'liter', 'unit']),
            'unit_price' => fake()->randomFloat(4, 1, 1000),
            'tax_rate' => fake()->randomFloat(2, 0, 20),
            'line_total' => fake()->randomFloat(4, 1, 100000),
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
