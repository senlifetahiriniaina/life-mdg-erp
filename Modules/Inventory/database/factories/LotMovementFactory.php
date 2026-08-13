<?php

declare(strict_types=1);

namespace Modules\Inventory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Models\Lot;
use Modules\Inventory\Models\LotMovement;

/** @extends Factory<LotMovement> */
class LotMovementFactory extends Factory
{
    protected $model = LotMovement::class;

    public function definition(): array
    {
        return [
            'lot_id' => Lot::factory(),
            'movement_type' => fake()->randomElement(['receipt', 'issue', 'transfer', 'adjustment']),
            'quantity' => fake()->randomFloat(4, 1, 100),
            'reference' => fake()->optional()->bothify('REF-####'),
            'warehouse_from_id' => null,
            'warehouse_to_id' => null,
            'notes' => null,
        ];
    }
}
