<?php

declare(strict_types=1);

namespace Modules\Inventory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Models\PickingWave;
use Modules\Inventory\Models\PickLine;
use Modules\Inventory\Models\Product;

/** @extends Factory<PickLine> */
class PickLineFactory extends Factory
{
    protected $model = PickLine::class;

    public function definition(): array
    {
        return [
            'wave_id' => PickingWave::factory(),
            'product_id' => Product::factory(),
            'warehouse_location' => fake()->optional()->bothify('A##-B##'),
            'qty_requested' => fake()->randomFloat(2, 1, 50),
            'qty_picked' => '0.00',
            'status' => 'pending',
        ];
    }
}
