<?php

namespace Modules\Analytics\Database\Factories;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Analytics\Models\RecommendationModel;

class RecommendationModelFactory extends Factory
{
    protected $model = RecommendationModel::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'model_name' => fake()->words(3, true),
            'recommendation_type' => fake()->word(),
            'algorithm' => fake()->word(),
            'status' => fake()->randomElement(['training', 'active', 'archived']),
            'description' => fake()->sentence(),
            'configuration' => [
                'top_n' => fake()->numberBetween(5, 20),
                'min_score' => fake()->randomFloat(2, 0, 1),
            ],
            'coverage_percentage' => fake()->randomFloat(2, 0, 100),
            'recommendation_count' => fake()->numberBetween(0, 10000),
            'click_through_count' => fake()->numberBetween(0, 1000),
            'ctr' => fake()->randomFloat(4, 0, 1),
            'last_trained_at' => fake()->dateTimeBetween('-60 days', 'now'),
            'created_by' => User::factory(),
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
            'status' => 'archived',
        ]);
    }
}
