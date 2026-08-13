<?php

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\ApprovalInstance;

class ApprovalInstanceFactory extends Factory
{
    protected $model = ApprovalInstance::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'workflow_id' => fake()->word(),
            'subject_type' => fake()->word(),
            'subject_id' => fake()->word(),
            'current_step' => fake()->word(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'initiated_by' => fake()->word(),
            'completed_at' => fake()->word(),
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