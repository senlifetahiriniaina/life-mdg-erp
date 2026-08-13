<?php

namespace Modules\Inventory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\StockMovement;
use Modules\Inventory\Models\Warehouse;

class StockMovementFactory extends Factory
{
    protected $model = StockMovement::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'warehouse_id' => Warehouse::factory(),
            'type' => $this->faker->randomElement(['in', 'out', 'adjustment', 'return', 'transfer']),
            'quantity' => $this->faker->numberBetween(-100, 100),
            'reference_type' => null,
            'reference_id' => null,
            'reason' => $this->faker->sentence(),
            'created_by' => null,
        ];
    }
}
