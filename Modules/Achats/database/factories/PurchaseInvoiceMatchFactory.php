<?php

namespace Modules\Achats\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Achats\Models\PurchaseInvoiceMatch;

class PurchaseInvoiceMatchFactory extends Factory
{
    protected $model = PurchaseInvoiceMatch::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'tenant_id' => fake()->word(),
            'purchase_receipt_id' => fake()->word(),
            'purchase_order_id' => fake()->word(),
            'invoice_id' => fake()->word(),
            'quantity_variance' => fake()->word(),
            'price_variance' => fake()->word(),
            'match_result' => fake()->word(),
            'mismatch_details' => fake()->word(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'resolved_by' => fake()->word(),
            'resolved_at' => fake()->word(),
            'resolution_notes' => fake()->word(),
            'name' => fake()->word(),
            'title' => fake()->word(),
            'description' => fake()->text(),
            'slug' => fake()->slug(),
            'code' => fake()->bothify('??-##'),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'amount' => fake()->randomFloat(2, 0, 1000),
            'quantity' => fake()->numberBetween(1, 100),
            'price' => fake()->randomFloat(2, 0, 1000),
            'cost' => fake()->randomFloat(2, 0, 1000),
            'is_active' => true,
            'notes' => fake()->text(),
        ];
    }

    /**
     * Indicate model is inactive
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate model is archived
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'archived_at' => now(),
        ]);
    }
}