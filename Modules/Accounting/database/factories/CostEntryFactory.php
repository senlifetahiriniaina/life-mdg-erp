<?php

namespace Modules\Accounting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\CostEntry;

class CostEntryFactory extends Factory
{
    protected $model = CostEntry::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'tenant_id' => fake()->word(),
            'category_code' => fake()->word(),
            'amount' => fake()->randomFloat(2, 0, 1000),
            'currency' => fake()->word(),
            'amount_xof' => fake()->word(),
            'allocatable_type' => fake()->word(),
            'allocatable_id' => fake()->word(),
            'source_module' => fake()->word(),
            'source_type' => fake()->word(),
            'source_id' => fake()->word(),
            'description' => fake()->text(),
            'period' => fake()->word(),
            'fiscal_year' => fake()->word(),
            'cost_driver' => fake()->word(),
            'units' => fake()->word(),
            'unit_cost' => fake()->word(),
            'is_estimated' => fake()->word(),
            'is_allocated' => fake()->word(),
            'allocated_at' => fake()->word(),
            'created_by' => fake()->word(),
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