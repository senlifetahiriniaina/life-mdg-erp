<?php

declare(strict_types=1);

namespace Modules\Inventory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Models\CostingSheet;

class CostingSheetFactory extends Factory
{
    protected $model = CostingSheet::class;

    public function definition(): array
    {
        return [
            'reference' => 'DEV-' . now()->format('Y') . '-' . strtoupper(uniqid()),
            'name' => $this->faker->words(3, true),
            'quantity' => $this->faker->numberBetween(100, 5000),
            'base_currency' => 'MGA',
            'status' => 'draft',
            'version' => 1,
            'production_minutes' => $this->faker->randomFloat(2, 20, 120),
            'minute_cost' => $this->faker->randomFloat(2, 50, 200),
            'fixed_cost_coefficient' => $this->faker->randomFloat(2, 1000, 10000),
        ];
    }
}
