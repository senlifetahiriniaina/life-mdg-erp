<?php

namespace Modules\Core\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\app\Models\ApprovalDecision;

class ApprovalDecisionFactory extends Factory
{
    protected $model = ApprovalDecision::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'instance_id' => fake()->word(),
            'step_order' => fake()->word(),
            'approver_id' => fake()->word(),
            'decision' => fake()->word(),
            'comment' => fake()->word(),
            'decided_at' => fake()->word(),
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