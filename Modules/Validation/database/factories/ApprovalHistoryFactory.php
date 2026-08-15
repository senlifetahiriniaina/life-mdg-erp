<?php

namespace Modules\Validation\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Validation\Models\ApprovalHistory;
use Modules\Validation\Models\ApprovalRequest;

class ApprovalHistoryFactory extends Factory
{
    protected $model = ApprovalHistory::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'request_id' => ApprovalRequest::factory(),
            'action' => fake()->randomElement(['approved', 'rejected']),
            'old_status' => fake()->randomElement(['pending', 'approved', 'rejected']),
            'new_status' => fake()->randomElement(['pending', 'approved', 'rejected']),
            'changed_by' => User::factory(),
            'changed_at' => fake()->dateTime(),
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
