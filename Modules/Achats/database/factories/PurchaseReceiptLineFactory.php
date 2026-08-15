<?php

namespace Modules\Achats\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Achats\Models\PurchaseOrderLine;
use Modules\Achats\Models\PurchaseReceipt;
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
            'receipt_id' => PurchaseReceipt::factory(),
            'purchase_order_line_id' => PurchaseOrderLine::factory(),
            'quantity_received' => fake()->randomFloat(4, 1, 500),
            'quality_status' => fake()->randomElement(['good', 'damaged', 'missing']),
            'variance_qty' => fake()->randomFloat(4, -10, 10),
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
