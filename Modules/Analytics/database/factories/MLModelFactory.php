<?php

namespace Modules\Analytics\Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Analytics\Models\MLModel;

class MLModelFactory extends Factory
{
    protected $model = MLModel::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            // Legacy NOT NULL column from the original migration, superseded by
            // `model_name` in the model's real $fillable but never made nullable —
            // still required by the schema, so it must be populated on insert.
            'name' => fake()->words(3, true),
            'company_id' => Company::factory(),
            'model_name' => fake()->words(3, true),
            'model_category' => fake()->randomElement(['prediction', 'recommendation', 'anomaly']),
            'framework' => fake()->randomElement(['sklearn', 'xgboost', 'lightgbm', 'prophet', 'custom']),
            'status' => fake()->randomElement(['development', 'staging', 'production']),
            'hyperparameters' => [
                'learning_rate' => fake()->randomFloat(3, 0.001, 0.3),
                'max_depth' => fake()->numberBetween(3, 12),
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
