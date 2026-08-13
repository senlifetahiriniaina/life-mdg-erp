<?php

namespace Modules\Sales\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Sales\app\Models\SalesOrderLine;

class SalesOrderLineFactory extends Factory
{
    protected $model = SalesOrderLine::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'sales_order_id' => fake()->word(),
            'product_id' => fake()->word(),
            'description' => fake()->text(),
            'quantity' => fake()->numberBetween(1, 100),
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