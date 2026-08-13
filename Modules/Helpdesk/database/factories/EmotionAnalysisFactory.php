<?php

namespace Modules\Helpdesk\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Helpdesk\Models\EmotionAnalysis;

class EmotionAnalysisFactory extends Factory
{
    protected $model = EmotionAnalysis::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'ticket_id' => fake()->word(),
            'sentiment_score_id' => fake()->word(),
            'anger_score' => fake()->word(),
            'frustration_score' => fake()->word(),
            'satisfaction_score' => fake()->word(),
            'confusion_score' => fake()->word(),
            'urgency_score' => fake()->word(),
            'disappointment_score' => fake()->word(),
            'dominant_emotion' => fake()->word(),
            'emotional_state' => fake()->word(),
            'emotional_intensity' => fake()->word(),
            'sentiment_shift_detected' => fake()->word(),
            'emotion_sequence' => fake()->word(),
            'context_notes' => fake()->word(),
            'analyzed_at' => fake()->word(),
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