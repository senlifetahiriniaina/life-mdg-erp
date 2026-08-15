<?php

namespace Modules\Analytics\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Analytics\Models\MLModelVersion;
use Modules\Analytics\Models\ModelMetric;

class ModelMetricFactory extends Factory
{
    protected $model = ModelMetric::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'ml_model_version_id' => MLModelVersion::factory(),
            'metric_name' => fake()->randomElement(['accuracy', 'precision', 'recall', 'f1', 'auc', 'rmse', 'mae']),
            'metric_value' => fake()->randomFloat(6, 0, 1),
            'dataset_type' => fake()->randomElement(['train', 'validation', 'test']),
            'breakdown' => [
                'by_class' => [
                    'positive' => fake()->randomFloat(2, 0, 1),
                    'negative' => fake()->randomFloat(2, 0, 1),
                ],
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
