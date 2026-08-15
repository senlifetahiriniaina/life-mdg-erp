<?php

namespace Modules\Validation\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Validation\Models\ApprovalRequest;
use Modules\Validation\Models\ApprovalWorkflow;

class ApprovalRequestFactory extends Factory
{
    protected $model = ApprovalRequest::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'workflow_id' => ApprovalWorkflow::factory(),
            'approvable_type' => fake()->randomElement(['invoice', 'purchase_order']),
            'approvable_id' => fake()->numberBetween(1, 1000),
            'status' => fake()->randomElement(['pending', 'approved', 'rejected']),
            'requested_by' => User::factory(),
            'approved_by' => null,
            'approved_at' => null,
            'rejected_at' => null,
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
