<?php

declare(strict_types=1);

namespace Modules\Inventory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Models\Location;
use Modules\Inventory\Models\Warehouse;

class LocationFactory extends Factory
{
    protected $model = Location::class;

    public function definition(): array
    {
        return [
            'warehouse_id' => Warehouse::factory(),
            'name' => $this->faker->bothify('Zone ?-##'),
            'code' => strtoupper($this->faker->bothify('?-##-##')),
        ];
    }
}
