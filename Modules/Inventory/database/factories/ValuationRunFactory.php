<?php

declare(strict_types=1);

namespace Modules\Inventory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Models\ValuationRun;

/** @extends Factory<ValuationRun> */
class ValuationRunFactory extends Factory
{
    protected $model = ValuationRun::class;

    public function definition(): array
    {
        return [
            'name' => 'Valuation '.fake()->date(),
            'method' => 'fifo',
            'valuation_date' => now()->toDateString(),
            'status' => 'draft',
            'total_value' => 0,
            'product_count' => 0,
            'results' => null,
            'created_by' => null,
        ];
    }
}
