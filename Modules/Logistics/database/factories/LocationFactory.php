<?php

declare(strict_types=1);

namespace Modules\Logistics\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Logistics\Models\Location;

/** @extends Factory<Location> */
class LocationFactory extends Factory
{
    protected $model = Location::class;

    public function definition(): array
    {
        $row = strtoupper(fake()->randomLetter());
        $aisle = fake()->numberBetween(1, 20);
        $level = fake()->numberBetween(1, 5);
        $bin = fake()->numberBetween(1, 10);

        return [
            'warehouse_id' => null,
            'parent_id' => null,
            'name' => fake()->bothify('Zone ??'),
            'code' => sprintf('%s-%02d-%02d-%02d', $row, $aisle, $level, $bin),
            'type' => 'bin',
            'location_class' => 'storage',
            'capacity_units' => fake()->optional()->randomFloat(2, 10, 500),
            'occupied_units' => 0,
            'max_weight_kg' => fake()->optional()->randomFloat(2, 100, 5000),
            'temperature_class' => null,
            'is_active' => true,
        ];
    }

    public function zone(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'zone',
            'parent_id' => null,
        ]);
    }

    public function aisle(): static
    {
        return $this->state(fn (array $attributes) => ['type' => 'aisle']);
    }

    public function rack(): static
    {
        return $this->state(fn (array $attributes) => ['type' => 'rack']);
    }

    public function bin(): static
    {
        return $this->state(fn (array $attributes) => ['type' => 'bin']);
    }

    public function cold(): static
    {
        return $this->state(fn (array $attributes) => [
            'location_class' => 'cold',
            'temperature_class' => fake()->randomElement(['chilled', 'frozen']),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
