<?php

declare(strict_types=1);

namespace Modules\Inventory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\TransferOrder;
use Modules\Inventory\Models\TransferOrderLine;

/** @extends Factory<TransferOrderLine> */
class TransferOrderLineFactory extends Factory
{
    protected $model = TransferOrderLine::class;

    public function definition(): array
    {
        return [
            'transfer_order_id' => TransferOrder::factory(),
            'product_id' => Product::factory(),
            'requested_quantity' => fake()->randomFloat(2, 10, 500),
            'approved_quantity' => null,
            'shipped_quantity' => 0,
            'received_quantity' => 0,
            'unit_cost' => fake()->randomFloat(2, 5, 200),
        ];
    }
}
