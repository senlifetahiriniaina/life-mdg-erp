<?php

namespace Modules\Inventory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Models\WarehouseStock;

class WarehouseStockFactory extends Factory
{
    protected $model = WarehouseStock::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'warehouse_id' => Warehouse::factory(),
            'location_id' => null,
            'quantity' => $this->faker->numberBetween(0, 500),
            'reserved_quantity' => 0,
            'avg_cost' => $this->faker->randomFloat(2, 1, 100),
        ];
    }
}
