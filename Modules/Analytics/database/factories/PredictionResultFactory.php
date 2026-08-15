<?php

namespace Modules\Analytics\Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Analytics\Models\PredictionModel;
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
            'prediction_model_id' => PredictionModel::factory(),
            'company_id' => Company::factory(),
            'prediction_score' => fake()->randomFloat(4, 0, 1),
            'prediction_class' => fake()->randomElement(['churn', 'retain', 'high_risk', 'low_risk']),
            'feature_contributions' => [
                'tenure' => fake()->randomFloat(2, -1, 1),
                'usage' => fake()->randomFloat(2, -1, 1),
            ],
            'metadata' => [
                'model_version' => fake()->numerify('v#.#'),
            ],
            'predicted_at' => fake()->dateTimeBetween('-30 days', 'now'),
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
