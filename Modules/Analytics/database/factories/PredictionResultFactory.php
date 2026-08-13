<?php

namespace Modules\Analytics\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Analytics\Models\PredictionResult;

class PredictionResultFactory extends Factory
{
    protected $model = PredictionResult::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'prediction_model_id' => fake()->word(),
            'company_id' => fake()->word(),
            'predictable_type' => fake()->word(),
            'predictable_id' => fake()->word(),
            'prediction_score' => fake()->word(),
            'prediction_class' => fake()->word(),
            'feature_contributions' => fake()->word(),
            'metadata' => fake()->word(),
            'predicted_at' => fake()->word(),
            'actual_outcome_at' => fake()->word(),
            'actual_outcome' => fake()->word(),
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