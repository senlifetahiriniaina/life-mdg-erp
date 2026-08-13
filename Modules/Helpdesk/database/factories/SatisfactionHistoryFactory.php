<?php

namespace Modules\Helpdesk\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Helpdesk\app\Models\SatisfactionHistory;

class SatisfactionHistoryFactory extends Factory
{
    protected $model = SatisfactionHistory::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'ticket_id' => fake()->word(),
            'satisfaction_prediction_id' => fake()->word(),
            'predicted_score' => fake()->word(),
            'actual_score' => fake()->word(),
            'prediction_error' => fake()->word(),
            'prediction_accurate' => fake()->word(),
            'satisfaction_category' => fake()->word(),
            'improvement_actions' => fake()->word(),
            'score_improved' => fake()->word(),
            'score_improvement_points' => fake()->word(),
            'model_feedback' => fake()->word(),
            'model_learning_impact' => fake()->word(),
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