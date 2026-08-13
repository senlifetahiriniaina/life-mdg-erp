<?php

namespace Modules\Achats\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Achats\app\Models\PurchaseOrder;

class PurchaseOrderFactory extends Factory
{
    protected $model = PurchaseOrder::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'po_number' => fake()->word(),
            'supplier_id' => fake()->word(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'currency' => fake()->word(),
            'subtotal' => fake()->word(),
            'shipping_cost' => fake()->word(),
            'notes' => fake()->text(),
            'approved_by' => fake()->word(),
            'approved_at' => fake()->word(),
            'created_by' => fake()->word(),
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