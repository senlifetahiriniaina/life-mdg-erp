<?php

namespace Modules\Validation\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Validation\app\Models\ApprovalAction;

class ApprovalActionFactory extends Factory
{
    protected $model = ApprovalAction::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'request_id' => fake()->word(),
            'approver_id' => fake()->word(),
            'action' => fake()->word(),
            'comment' => fake()->word(),
            'acted_at' => fake()->word(),
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