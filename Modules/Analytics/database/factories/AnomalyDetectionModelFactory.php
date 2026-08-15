<?php

namespace Modules\Analytics\Database\Factories;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Analytics\Models\AnomalyDetectionModel;

class AnomalyDetectionModelFactory extends Factory
{
    protected $model = AnomalyDetectionModel::class;

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
            'anomaly_type' => fake()->randomElement(['transaction', 'performance', 'behavioral', 'system']),
            'algorithm' => fake()->randomElement(['isolation_forest', 'local_outlier_factor', 'mahalanobis']),
            'status' => 'active',
            'description' => fake()->sentence(),
            'configuration' => [
                'window_size' => fake()->numberBetween(10, 100),
                'sensitivity' => fake()->randomFloat(2, 0, 1),
            ],
            'anomaly_threshold' => fake()->randomFloat(4, 0, 1),
            'detection_count' => fake()->numberBetween(0, 500),
            'true_positive_count' => fake()->numberBetween(0, 500),
            'precision' => fake()->randomFloat(4, 0, 1),
            'last_retrained_at' => fake()->dateTimeBetween('-60 days', 'now'),
            'created_by' => User::factory(),
        ];
    }

    /**
     * Indicate model is inactive
     */
    public function inactive(): static
    {
        // `is_active` is a real column on this table (default true) even though it
        // isn't in the model's $fillable — safe to set directly via state().
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
        ]);
    }
}
