<?php

namespace Modules\Achats\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Achats\Models\PoInventoryMapping;

class PoInventoryMappingFactory extends Factory
{
    protected $model = PoInventoryMapping::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'purchase_order_id' => fake()->word(),
        ];
    }

    /**
     * Indicate model is inactive
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
        ]);
    }

    /**
     * Indicate model is archived
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
        ]);
    }
}