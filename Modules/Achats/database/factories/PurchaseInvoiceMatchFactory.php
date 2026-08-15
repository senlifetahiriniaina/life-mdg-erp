<?php

namespace Modules\Achats\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Achats\Models\PurchaseInvoiceMatch;
use Modules\Achats\Models\PurchaseOrder;
use Modules\Achats\Models\PurchaseReceipt;

class PurchaseInvoiceMatchFactory extends Factory
{
    protected $model = PurchaseInvoiceMatch::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'purchase_receipt_id' => PurchaseReceipt::factory(),
            'purchase_order_id' => PurchaseOrder::factory(),
            'quantity_variance' => fake()->randomFloat(4, -50, 50),
            'price_variance' => fake()->randomFloat(2, -500, 500),
            'match_result' => fake()->randomElement(['matched', 'quantity_mismatch', 'price_mismatch', 'both_mismatch']),
            'mismatch_details' => ['details' => fake()->sentence()],
            'status' => fake()->randomElement(['approved', 'flagged', 'resolved']),
            'resolved_by' => User::factory(),
            'resolved_at' => fake()->dateTime(),
            'resolution_notes' => fake()->text(),
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
