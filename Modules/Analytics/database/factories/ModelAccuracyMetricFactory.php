<?php

namespace Modules\Analytics\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Analytics\Models\ModelAccuracyMetric;
use Modules\Analytics\Models\PredictionModel;

class ModelAccuracyMetricFactory extends Factory
{
    protected $model = ModelAccuracyMetric::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'prediction_model_id' => PredictionModel::factory(),
            'metric_date' => fake()->dateTimeBetween('-90 days', 'now'),
            'metric_type' => fake()->randomElement(['accuracy', 'precision', 'recall', 'f1', 'auc']),
            'metric_value' => fake()->randomFloat(4, 0, 1),
            'sample_size' => fake()->numberBetween(100, 10000),
            'breakdown_by_segment' => [
                'segment_a' => fake()->randomFloat(2, 0, 1),
                'segment_b' => fake()->randomFloat(2, 0, 1),
            ],
            'data_period' => fake()->randomElement(['daily', 'weekly', 'monthly']),
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
