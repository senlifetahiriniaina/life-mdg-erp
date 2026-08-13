<?php

namespace Modules\Helpdesk\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Helpdesk\app\Models\NPSPredictor;

class NPSPredictorFactory extends Factory
{
    protected $model = NPSPredictor::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'ticket_id' => fake()->word(),
            'predicted_nps_score' => fake()->word(),
            'promoter_likelihood' => fake()->word(),
            'promoter_probability' => fake()->word(),
            'passive_probability' => fake()->word(),
            'detractor_probability' => fake()->word(),
            'nps_influencing_factors' => fake()->word(),
            'recommendation_likelihood' => fake()->word(),
            'recommendation_sentiment' => fake()->word(),
            'advocacy_score' => fake()->word(),
            'is_repeat_customer' => fake()->word(),
            'lifetime_value_segment' => fake()->word(),
            'customer_segment' => fake()->word(),
            'churn_risk_indicators' => fake()->word(),
            'churn_probability' => fake()->word(),
            'actual_nps_score' => fake()->word(),
            'predicted_at' => fake()->word(),
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