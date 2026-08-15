<?php

namespace Modules\Achats\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Achats\Models\PurchaseOrder;
use Modules\Achats\Models\PurchaseReceipt;

class PurchaseReceiptFactory extends Factory
{
    protected $model = PurchaseReceipt::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'purchase_order_id' => PurchaseOrder::factory(),
            'receipt_number' => fake()->unique()->bothify('RCT-#####'),
            'receipt_date' => fake()->date(),
            'received_by' => User::factory(),
            'warehouse_location' => fake()->word(),
            'total_received_value' => fake()->randomFloat(2, 100, 50000),
            'status' => fake()->randomElement(['draft', 'completed']),
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
