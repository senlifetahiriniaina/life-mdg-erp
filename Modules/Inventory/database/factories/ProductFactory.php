<?php

namespace Modules\Inventory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Models\Category;
use Modules\Inventory\Models\Product;
use Modules\Inventory\Models\Unit;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $costPrice = $this->faker->randomFloat(2, 10, 500);
        $salePrice = $costPrice * $this->faker->randomFloat(2, 1.2, 2.5);

        return [
            'sku' => strtoupper($this->faker->unique()->lexify('SKU????')),
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'category_id' => Category::factory(),
            'unit_id' => Unit::factory(),
            'cost_price' => $costPrice,
            'sale_price' => $salePrice,
            'selling_price' => $salePrice,
            'status' => 'active',
            'reorder_point' => $this->faker->numberBetween(10, 100),
            'reorder_qty' => $this->faker->numberBetween(50, 500),
            'is_active' => true,
        ];
    }
}
