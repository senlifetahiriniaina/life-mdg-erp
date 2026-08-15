<?php

namespace Modules\Achats\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Achats\Models\PoBudgetAllocation;
use Modules\Achats\Models\PurchaseOrder;

class PoBudgetAllocationFactory extends Factory
{
    protected $model = PoBudgetAllocation::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'purchase_order_id' => PurchaseOrder::factory(),
            'budget_type' => 'operational',
            'allocated_amount' => fake()->randomFloat(2, 1000, 100000),
            'spent_amount' => fake()->randomFloat(2, 0, 50000),
            'status' => fake()->randomElement(['active', 'released']),
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
