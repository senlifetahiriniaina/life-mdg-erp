<?php

declare(strict_types=1);

namespace Modules\Inventory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Models\CycleCount;
use Modules\Inventory\Models\CycleCountLine;
use Modules\Inventory\Models\Product;

class CycleCountLineFactory extends Factory
{
    protected $model = CycleCountLine::class;

    public function definition(): array
    {
        return [
            'cycle_count_id' => CycleCount::factory(),
            'product_id' => Product::factory(),
            'location_id' => null,
            'system_qty' => $this->faker->randomFloat(2, 0, 100),
            'counted_qty' => null,
            'variance' => null,
            'variance_value' => null,
            'status' => 'pending',
        ];
    }
}
