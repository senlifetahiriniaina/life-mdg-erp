<?php

namespace Modules\Inventory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Models\Warehouse;

class WarehouseFactory extends Factory
{
    protected $model = Warehouse::class;

    public function definition(): array
    {
        $code = 'WH'.mt_rand(1000, 9999);
        $types = ['main', 'transit', 'virtual'];

        return [
            'name' => 'Warehouse '.mt_rand(1, 999),
            'code' => $code,
            'type' => $types[array_rand($types)],
            'address' => 'Address 123',
            'city' => 'City',
            'country' => 'US',
            'is_active' => true,
        ];
    }
}
