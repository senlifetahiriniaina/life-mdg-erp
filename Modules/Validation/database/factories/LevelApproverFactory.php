<?php

namespace Modules\Validation\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Validation\Models\LevelApprover;

class LevelApproverFactory extends Factory
{
    protected $model = LevelApprover::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
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