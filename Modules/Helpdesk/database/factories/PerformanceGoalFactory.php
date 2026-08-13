<?php

namespace Modules\Helpdesk\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Helpdesk\Models\PerformanceGoal;

class PerformanceGoalFactory extends Factory
{
    protected $model = PerformanceGoal::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'weight' => fake()->word(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'title' => fake()->word(),
            'description' => fake()->text(),
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