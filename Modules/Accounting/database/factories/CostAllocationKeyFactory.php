<?php

namespace Modules\Accounting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\CostAllocationKey;

class CostAllocationKeyFactory extends Factory
{
    protected $model = CostAllocationKey::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'tenant_id' => fake()->word(),
            'name' => fake()->word(),
            'allocation_method' => fake()->word(),
            'source_pool' => fake()->word(),
            'target_type' => fake()->word(),
            'formula' => fake()->word(),
            'is_active' => true,
        ];
    }

    /**
     * Indicate model is inactive
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
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