<?php

namespace Modules\Accounting\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\app\Models\ApprovalStep;

class ApprovalStepFactory extends Factory
{
    protected $model = ApprovalStep::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'approval_id' => fake()->word(),
            'level' => fake()->word(),
            'required_role' => fake()->word(),
            'approver_id' => fake()->word(),
            'approved_at' => fake()->word(),
            'action' => fake()->word(),
            'comment' => fake()->word(),
            'threshold_amount' => fake()->word(),
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