<?php

declare(strict_types=1);

namespace Modules\Inventory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Models\CostLayer;

/** @extends Factory<CostLayer> */
class CostLayerFactory extends Factory
{
    protected $model = CostLayer::class;

    public function definition(): array
    {
        $qtyReceived = fake()->randomFloat(2, 10, 500);

        return [
            'method' => 'fifo',
            'quantity_received' => $qtyReceived,
            'quantity_remaining' => $qtyReceived,
            'unit_cost' => fake()->randomFloat(2, 5, 200),
            'total_cost' => 0,
            'received_at' => now(),
            'reference' => null,
            'is_exhausted' => false,
        ];
    }
}
