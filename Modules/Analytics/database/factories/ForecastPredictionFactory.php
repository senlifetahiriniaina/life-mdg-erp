<?php

namespace Modules\Analytics\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Analytics\Models\ForecastModel;
use Modules\Analytics\Models\ForecastPrediction;

class ForecastPredictionFactory extends Factory
{
    protected $model = ForecastPrediction::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'forecast_model_id' => ForecastModel::factory(),
            'tenant_id' => fake()->numberBetween(1, 100),
            'forecast_date' => fake()->dateTimeBetween('-30 days', '+90 days')->format('Y-m-d'),
            'predicted_value' => fake()->randomFloat(4, 0, 100000),
            'lower_bound' => fake()->randomFloat(4, 0, 100000),
            'predicted_upper_bound' => fake()->randomFloat(4, 0, 100000),
            'actual_value' => fake()->randomFloat(4, 0, 100000),
            'error_pct' => fake()->randomFloat(4, 0, 50),
            'confidence' => fake()->randomFloat(4, 0.5, 0.99),
            'metadata' => [
                'model_version' => fake()->numerify('v#.#'),
                'notes' => fake()->sentence(),
            ],
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
