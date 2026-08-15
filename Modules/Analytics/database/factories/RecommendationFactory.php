<?php

namespace Modules\Analytics\Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Analytics\Models\Recommendation;
use Modules\Analytics\Models\RecommendationModel;

class RecommendationFactory extends Factory
{
    protected $model = Recommendation::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'recommendation_model_id' => RecommendationModel::factory(),
            'company_id' => Company::factory(),
            'relevance_score' => fake()->randomFloat(4, 0, 1),
            'rank' => fake()->numberBetween(1, 20),
            'reason' => fake()->sentence(),
            'metadata' => [
                'algorithm' => fake()->randomElement(['collaborative_filtering', 'content_based']),
            ],
            'status' => fake()->randomElement(['pending', 'viewed', 'acted', 'dismissed']),
            'expires_at' => fake()->dateTimeBetween('now', '+30 days'),
        ];
    }

    /**
     * Indicate model is inactive
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
        ]);
    }

    /**
     * Indicate model is archived
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
        ]);
    }
}
