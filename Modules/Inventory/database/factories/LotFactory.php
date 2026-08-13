<?php

declare(strict_types=1);

namespace Modules\Inventory\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Models\Lot;

/** @extends Factory<Lot> */
class LotFactory extends Factory
{
    protected $model = Lot::class;

    public function definition(): array
    {
        return [
            'lot_number' => strtoupper(fake()->unique()->bothify('LOT-####-??')),
            'serial_number' => null,
            'manufacture_date' => fake()->optional()->date(),
            'expiry_date' => fake()->boolean(70) ? fake()->dateTimeBetween('now', '+2 years')->format('Y-m-d') : null,
            'quantity' => fake()->randomFloat(4, 0, 500),
            'status' => 'active',
            'warehouse_id' => null,
            'notes' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(['status' => 'active']);
    }

    public function expired(): static
    {
        return $this->state(['status' => 'expired']);
    }

    public function quarantine(): static
    {
        return $this->state(['status' => 'quarantine']);
    }

    public function depleted(): static
    {
        return $this->state(['status' => 'depleted', 'quantity' => 0]);
    }
}
