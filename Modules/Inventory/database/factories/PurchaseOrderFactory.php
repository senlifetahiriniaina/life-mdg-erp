<?php

declare(strict_types=1);

namespace Modules\Inventory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Models\PurchaseOrder;
use Modules\Inventory\Models\Supplier;

/** @extends Factory<PurchaseOrder> */
class PurchaseOrderFactory extends Factory
{
    protected $model = PurchaseOrder::class;

    public function definition(): array
    {
        return [
            'reference' => 'PO-'.$this->faker->unique()->numerify('########'),
            'supplier_id' => Supplier::factory(),
            'status' => 'draft',
            'currency' => 'USD',
            'subtotal' => 0,
            'tax_total' => 0,
            'shipping_cost' => 0,
            'grand_total' => 0,
        ];
    }
}
