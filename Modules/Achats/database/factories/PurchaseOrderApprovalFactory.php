<?php

namespace Modules\Achats\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Achats\Models\PurchaseOrder;
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
            'purchase_order_id' => PurchaseOrder::factory(),
            'approver_id' => User::factory(),
            'approval_level' => fake()->randomElement(['supervisor', 'manager', 'finance']),
            'status' => fake()->randomElement(['pending', 'approved', 'rejected']),
            'rejection_reason' => fake()->sentence(),
            'notes' => fake()->text(),
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
