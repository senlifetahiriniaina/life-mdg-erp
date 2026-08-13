<?php

namespace Modules\Inventory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Models\Unit;

class UnitFactory extends Factory
{
    protected $model = Unit::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->randomElement(['Piece', 'Kilogram', 'Gram', 'Liter', 'Milliliter', 'Meter', 'Box', 'Pack']) . '-' . uniqid(),
            'symbol' => $this->faker->randomElement(['pcs', 'kg', 'g', 'L', 'ml', 'm', 'box', 'pack']),
            'type' => $this->faker->randomElement(['unit', 'weight', 'volume', 'length']),
        ];
    }
}
