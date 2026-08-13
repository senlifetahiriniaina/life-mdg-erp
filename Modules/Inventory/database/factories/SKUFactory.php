<?php

namespace Modules\Inventory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Models\SKU;

class SKUFactory extends Factory
{
    protected $model = SKU::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->unique()->bothify('SKU-####')),
            'name' => $this->faker->words(2, true),
            'unit' => $this->faker->randomElement(['pcs', 'kg', 'box', 'm']),
            'reorder_level' => $this->faker->numberBetween(5, 50),
            'reorder_point' => $this->faker->numberBetween(5, 50),
            'reorder_qty' => $this->faker->numberBetween(50, 200),
            'is_active' => true,
        ];
    }
}
