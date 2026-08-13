<?php

declare(strict_types=1);

namespace Modules\Inventory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Models\PickingOrder;

/** @extends Factory<PickingOrder> */
class PickingOrderFactory extends Factory
{
    protected $model = PickingOrder::class;

    public function definition(): array
    {
        return [
            'reference' => 'PICK-'.$this->faker->unique()->numerify('######'),
            'warehouse_id' => 1,
            'type' => $this->faker->randomElement(['pick', 'replenish', 'transfer']),
            'status' => $this->faker->randomElement(['pending', 'in_progress', 'completed']),
            'source_type' => 'manual',
            'priority' => $this->faker->numberBetween(0, 3),
        ];
    }
}
