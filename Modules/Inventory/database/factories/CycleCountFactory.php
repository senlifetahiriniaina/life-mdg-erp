<?php

declare(strict_types=1);

namespace Modules\Inventory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Models\CycleCount;

/** @extends Factory<CycleCount> */
class CycleCountFactory extends Factory
{
    protected $model = CycleCount::class;

    public function definition(): array
    {
        return [
            'reference' => 'CC-'.$this->faker->unique()->numerify('######'),
            'warehouse_id' => 1,
            'status' => $this->faker->randomElement(['draft', 'in_progress', 'completed']),
            'count_date' => $this->faker->date(),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}
