<?php

declare(strict_types=1);

namespace Modules\Logistics\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Logistics\Models\Shipment;
use Modules\Logistics\Models\ShipmentLine;

/** @extends Factory<ShipmentLine> */
class ShipmentLineFactory extends Factory
{
    protected $model = ShipmentLine::class;

    public function definition(): array
    {
        return [
            'shipment_id' => Shipment::factory(),
            'description' => fake()->words(4, true),
            'quantity' => fake()->numberBetween(1, 500),
            'hs_code' => fake()->numerify('##########'),
            'unit_value' => fake()->randomFloat(2, 1, 5000),
            'weight_kg' => fake()->randomFloat(3, 0.1, 200),
            'currency' => 'USD',
        ];
    }
}
