<?php

namespace Modules\Helpdesk\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Helpdesk\Models\EscalationHistory;

class EscalationHistoryFactory extends Factory
{
    protected $model = EscalationHistory::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'ticket_id' => fake()->word(),
            'escalation_prediction_id' => fake()->word(),
            'predicted_urgency' => fake()->word(),
            'predicted_probability' => fake()->word(),
            'prediction_correct' => fake()->word(),
            'actual_escalation_probability' => fake()->word(),
            'actual_action' => fake()->word(),
            'was_escalated' => fake()->word(),
            'escalation_delay_minutes' => fake()->word(),
            'model_accuracy_impact' => fake()->word(),
            'feedback' => fake()->word(),
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