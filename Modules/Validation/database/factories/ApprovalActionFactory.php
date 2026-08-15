<?php

namespace Modules\Validation\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Validation\Models\ApprovalAction;
use Modules\Validation\Models\ApprovalRequest;

class ApprovalActionFactory extends Factory
{
    protected $model = ApprovalAction::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'request_id' => ApprovalRequest::factory(),
            'approver_id' => User::factory(),
            'action' => fake()->randomElement(['approved', 'rejected']),
            'comment' => fake()->sentence(),
            'acted_at' => fake()->dateTime(),
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
