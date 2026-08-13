<?php

namespace Modules\Validation\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Validation\Models\ApprovalRequest;

class ApprovalRequestFactory extends Factory
{
    protected $model = ApprovalRequest::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'workflow_id' => fake()->word(),
            'approvable_type' => fake()->word(),
            'approvable_id' => fake()->word(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'requested_by' => fake()->word(),
            'approved_by' => fake()->word(),
            'approved_at' => fake()->word(),
            'rejected_at' => fake()->word(),
            'amount' => fake()->randomFloat(2, 0, 1000),
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