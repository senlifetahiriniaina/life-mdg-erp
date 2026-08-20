<?php

declare(strict_types=1);

namespace Modules\Inventory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Models\ProductionOrder;

class ProductionOrderFactory extends Factory
{
    protected $model = ProductionOrder::class;

    public function definition(): array
    {
        return [
            'reference' => 'PRD-' . now()->format('Y') . '-' . strtoupper(uniqid()),
            'quantity' => $this->faker->numberBetween(50, 2000),
            'status' => 'draft',
        ];
    }
}
