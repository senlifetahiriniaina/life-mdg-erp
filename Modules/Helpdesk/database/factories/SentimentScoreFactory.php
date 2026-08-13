<?php

namespace Modules\Helpdesk\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Helpdesk\Models\SentimentScore;

class SentimentScoreFactory extends Factory
{
    protected $model = SentimentScore::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'ticket_id' => fake()->word(),
            'sentiment_model_id' => fake()->word(),
            'language_detected' => fake()->word(),
            'sentiment' => fake()->word(),
            'positive_score' => fake()->word(),
            'negative_score' => fake()->word(),
            'neutral_score' => fake()->word(),
            'confidence' => fake()->word(),
            'analyzed_text' => fake()->word(),
            'tokens' => fake()->word(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'error_message' => fake()->word(),
            'analyzed_at' => fake()->word(),
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