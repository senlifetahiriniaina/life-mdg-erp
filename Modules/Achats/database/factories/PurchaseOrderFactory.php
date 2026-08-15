<?php

namespace Modules\Achats\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Achats\Models\PurchaseOrder;
use Modules\Achats\Models\Supplier;

class PurchaseOrderFactory extends Factory
{
    protected $model = PurchaseOrder::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'po_number' => fake()->unique()->bothify('PO-#####'),
            'supplier_id' => Supplier::factory(),
            'status' => fake()->randomElement(['draft', 'submitted', 'approved', 'received', 'rejected']),
            'order_date' => fake()->date(),
            'currency' => fake()->randomElement(['XOF', 'XAF', 'MGA', 'USD', 'EUR']),
            'subtotal' => fake()->randomFloat(2, 100, 100000),
            'tax_amount' => fake()->randomFloat(2, 0, 20000),
            'shipping_cost' => fake()->randomFloat(2, 0, 5000),
            'total' => fake()->randomFloat(2, 100, 125000),
            'notes' => fake()->text(),
            'requested_by' => User::factory(),
            'approved_by' => User::factory(),
            'approved_at' => fake()->dateTime(),
            'created_by' => User::factory(),
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
