<?php

namespace Modules\Validation\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Validation\Models\ApprovalHistory;

class ApprovalHistoryFactory extends Factory
{
    protected $model = ApprovalHistory::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'request_id' => fake()->word(),
            'action' => fake()->word(),
            'old_status' => fake()->word(),
            'new_status' => fake()->word(),
            'changed_by' => fake()->word(),
            'changed_at' => fake()->word(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
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