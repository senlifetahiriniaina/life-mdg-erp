<?php

namespace Modules\Helpdesk\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Helpdesk\app\Models\SatisfactionPrediction;

class SatisfactionPredictionFactory extends Factory
{
    protected $model = SatisfactionPrediction::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'ticket_id' => fake()->word(),
            'satisfaction_model_id' => fake()->word(),
            'predicted_satisfaction_score' => fake()->word(),
            'confidence' => fake()->word(),
            'satisfaction_category' => fake()->word(),
            'contributing_factors' => fake()->word(),
            'risk_factors' => fake()->word(),
            'improvement_suggestions' => fake()->word(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'prediction_correct' => fake()->word(),
            'actual_satisfaction_score' => fake()->word(),
            'prediction_error' => fake()->word(),
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