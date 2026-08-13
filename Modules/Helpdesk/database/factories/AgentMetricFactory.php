<?php

namespace Modules\Helpdesk\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Helpdesk\Models\AgentMetric;

class AgentMetricFactory extends Factory
{
    protected $model = AgentMetric::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'agent_id' => fake()->word(),
            'metric_date' => fake()->word(),
            'tickets_handled' => fake()->word(),
            'tickets_resolved' => fake()->word(),
            'first_contact_resolution_count' => fake()->word(),
            'first_contact_resolution_rate' => fake()->word(),
            'avg_resolution_time_minutes' => fake()->word(),
            'avg_response_time_seconds' => fake()->word(),
            'avg_handle_time_seconds' => fake()->word(),
            'avg_satisfaction_rating' => fake()->word(),
            'satisfaction_survey_responses' => fake()->word(),
            'avg_sentiment_improvement' => fake()->word(),
            'escalation_count' => fake()->word(),
            'escalation_rate' => fake()->word(),
            'repeat_contact_count' => fake()->word(),
            'repeat_contact_rate' => fake()->word(),
            'quality_audit_score' => fake()->word(),
            'nps_detractor_count' => fake()->word(),
            'nps_passive_count' => fake()->word(),
            'nps_promoter_count' => fake()->word(),
            'nps_score' => fake()->word(),
            'productivity_score' => fake()->word(),
            'quality_score' => fake()->word(),
            'overall_performance_score' => fake()->word(),
            'performance_rating' => fake()->word(),
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