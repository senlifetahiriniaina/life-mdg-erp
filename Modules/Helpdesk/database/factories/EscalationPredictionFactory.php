<?php

namespace Modules\Helpdesk\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Helpdesk\Models\EscalationPrediction;

class EscalationPredictionFactory extends Factory
{
    protected $model = EscalationPrediction::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'ticket_id' => fake()->word(),
            'escalation_model_id' => fake()->word(),
            'urgency_score' => fake()->word(),
            'escalation_probability' => fake()->word(),
            'confidence' => fake()->word(),
            'recommended_action' => fake()->word(),
            'escalation_level' => fake()->word(),
            'estimated_resolution_hours' => fake()->word(),
            'contributing_factors' => fake()->word(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'escalated' => fake()->word(),
            'escalated_at' => fake()->word(),
            'notes' => fake()->text(),
            'predicted_at' => fake()->word(),
            'name' => fake()->word(),
            'title' => fake()->word(),
            'description' => fake()->text(),
            'slug' => fake()->slug(),
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