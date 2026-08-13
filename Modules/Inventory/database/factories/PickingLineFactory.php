<?php

declare(strict_types=1);

namespace Modules\Inventory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\PickingLine;
use Modules\Inventory\Models\PickingOrder;
use Modules\Inventory\Models\Product;

class PickingLineFactory extends Factory
{
    protected $model = PickingLine::class;

    public function definition(): array
    {
        return [
            'picking_order_id' => PickingOrder::factory(),
            'product_id' => Product::factory(),
            'location_id' => Location::factory(),
            'quantity_requested' => $this->faker->randomFloat(2, 1, 100),
            'quantity_picked' => 0,
            'status' => 'pending',
        ];
    }
}
