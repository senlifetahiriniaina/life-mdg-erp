<?php

namespace Modules\Achats\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
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
                        'purchase_order_id' => fake()->word(),
            'description' => fake()->text(),
            'quantity' => fake()->numberBetween(1, 100),
            'unit' => fake()->word(),
            'unit_price' => fake()->word(),
            'tax_rate' => fake()->word(),
            'line_total' => fake()->word(),
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