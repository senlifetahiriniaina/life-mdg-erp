<?php

declare(strict_types=1);

namespace Modules\CRM\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\CRM\Models\ProductBundle;

class ProductBundleFactory extends Factory
{
    protected $model = ProductBundle::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true).' Bundle',
            'description' => fake()->sentence(),
            'base_price' => fake()->randomFloat(2, 100, 10000),
            'discount_pct' => fake()->randomFloat(2, 0, 30),
            'items' => [
                [
                    'product_id' => 1,
                    'name' => fake()->words(2, true),
                    'quantity' => 1,
                    'unit_price' => fake()->randomFloat(2, 50, 5000),
                ],
            ],
            'active' => true,
        ];
    }
}
