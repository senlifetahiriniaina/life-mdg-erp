<?php

namespace Modules\Achats\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Achats\Models\PurchaseReceiptLine;

class PurchaseReceiptLineFactory extends Factory
{
    protected $model = PurchaseReceiptLine::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'receipt_id' => fake()->word(),
            'quantity_received' => fake()->word(),
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