<?php

namespace Modules\Helpdesk\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Helpdesk\Models\AgentCoachingRecommendation;

class AgentCoachingRecommendationFactory extends Factory
{
    protected $model = AgentCoachingRecommendation::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'agent_id' => fake()->word(),
            'generated_by' => fake()->word(),
            'recommendation_type' => fake()->word(),
            'priority' => fake()->word(),
            'description' => fake()->text(),
            'target_metric' => fake()->word(),
            'current_performance' => fake()->word(),
            'target_performance' => fake()->word(),
            'recommended_actions' => fake()->word(),
            'training_program' => fake()->word(),
            'resources' => fake()->word(),
            'target_completion_date' => fake()->word(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'acknowledged_at' => fake()->word(),
            'completed_at' => fake()->word(),
            'expected_improvement' => fake()->word(),
            'actual_improvement' => fake()->word(),
            'feedback_notes' => fake()->word(),
            'name' => fake()->word(),
            'title' => fake()->word(),
            'slug' => fake()->slug(),
            'code' => fake()->bothify('??-##'),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'amount' => fake()->randomFloat(2, 0, 1000),
            'quantity' => fake()->numberBetween(1, 100),
            'price' => fake()->randomFloat(2, 0, 1000),
            'cost' => fake()->randomFloat(2, 0, 1000),
            'is_active' => true,
            'notes' => fake()->text(),
        ];
    }

    /**
     * Indicate model is inactive
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate model is archived
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'archived_at' => now(),
        ]);
    }
}