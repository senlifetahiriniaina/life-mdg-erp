<?php

namespace Modules\Helpdesk\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Helpdesk\Models\ResponsePerformance;

class ResponsePerformanceFactory extends Factory
{
    protected $model = ResponsePerformance::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'template_id' => fake()->word(),
            'variant_id' => fake()->word(),
            'ticket_id' => fake()->word(),
            'response_type' => fake()->word(),
            'satisfaction_rating' => fake()->word(),
            'feedback_category' => fake()->word(),
            'response_time_seconds' => fake()->word(),
            'ticket_resolution_time_minutes' => fake()->word(),
            'issue_resolved' => fake()->word(),
            'resolution_effectiveness' => fake()->word(),
            'follow_up_count' => fake()->word(),
            'required_escalation' => fake()->word(),
            'required_additional_response' => fake()->word(),
            'customer_sentiment_after' => fake()->word(),
            'metrics' => fake()->word(),
            'notes' => fake()->text(),
            'name' => fake()->word(),
            'title' => fake()->word(),
            'description' => fake()->text(),
            'slug' => fake()->slug(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'code' => fake()->bothify('??-##'),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'amount' => fake()->randomFloat(2, 0, 1000),
            'quantity' => fake()->numberBetween(1, 100),
            'price' => fake()->randomFloat(2, 0, 1000),
            'cost' => fake()->randomFloat(2, 0, 1000),
            'is_active' => true,
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