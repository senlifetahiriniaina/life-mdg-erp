<?php

namespace Modules\Analytics\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Analytics\Models\PredictionInput;
use Modules\Analytics\Models\PredictionModel;

class PredictionInputFactory extends Factory
{
    protected $model = PredictionInput::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'prediction_model_id' => PredictionModel::factory(),
            'feature_name' => fake()->word(),
            'feature_type' => fake()->randomElement(['numeric', 'categorical', 'boolean', 'datetime']),
            'data_source' => fake()->word(),
            'field_mapping' => fake()->word(),
            'transformation' => [
                'type' => fake()->randomElement(['none', 'normalize', 'one_hot', 'log']),
                'params' => [],
            ],
            'importance_score' => fake()->randomFloat(4, 0, 1),
            'is_required' => fake()->boolean(),
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
