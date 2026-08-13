<?php

namespace Modules\Achats\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Achats\Models\PurchaseOrderApproval;

class PurchaseOrderApprovalFactory extends Factory
{
    protected $model = PurchaseOrderApproval::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'purchase_order_id' => fake()->word(),
            'approver_id' => fake()->word(),
            'approval_level' => fake()->word(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'rejection_reason' => fake()->word(),
            'notes' => fake()->text(),
            'name' => fake()->word(),
            'title' => fake()->word(),
            'description' => fake()->text(),
            'slug' => fake()->slug(),
            'code' => fake()->bothify('??-##'),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'amount' => fake()->randomFloat(2, 0, 1000),
            'quantity' => fake()->numberBetween(1, 100),
            'price' => fake()->randomFloat(2, 0, 1000),
            'cost' => fake()->randomFloat(2, 0, 1000),
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
            'archived_at' => now(),
        ]);
    }
}