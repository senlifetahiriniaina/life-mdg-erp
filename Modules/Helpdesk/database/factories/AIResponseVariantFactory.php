<?php

namespace Modules\Helpdesk\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Helpdesk\app\Models\AIResponseVariant;

class AIResponseVariantFactory extends Factory
{
    protected $model = AIResponseVariant::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'template_id' => fake()->word(),
            'variant_type' => fake()->word(),
            'content' => fake()->word(),
            'target_sentiment' => fake()->word(),
            'target_emotion' => fake()->word(),
            'target_context' => fake()->word(),
            'relevance_score' => fake()->word(),
            'triggers' => fake()->word(),
            'avg_satisfaction_rating' => fake()->word(),
            'usage_count' => fake()->word(),
            'positive_feedback_count' => fake()->word(),
            'negative_feedback_count' => fake()->word(),
            'ai_generated' => fake()->word(),
            'generation_model' => fake()->word(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'generated_at' => fake()->word(),
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