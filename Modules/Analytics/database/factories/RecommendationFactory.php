<?php

namespace Modules\Analytics\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Analytics\app\Models\Recommendation;

class RecommendationFactory extends Factory
{
    protected $model = Recommendation::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'recommendation_model_id' => fake()->word(),
            'company_id' => fake()->word(),
            'recipient_type' => fake()->word(),
            'recipient_id' => fake()->word(),
            'recommended_type' => fake()->word(),
            'recommended_id' => fake()->word(),
            'relevance_score' => fake()->word(),
            'rank' => fake()->word(),
            'reason' => fake()->word(),
            'metadata' => fake()->word(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'viewed_at' => fake()->word(),
            'clicked_at' => fake()->word(),
            'acted_at' => fake()->word(),
            'expires_at' => fake()->word(),
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