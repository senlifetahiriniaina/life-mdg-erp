<?php

namespace Modules\Analytics\Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Analytics\Models\AnomalyDetectionModel;
use Modules\Analytics\Models\DetectedAnomaly;

class DetectedAnomalyFactory extends Factory
{
    protected $model = DetectedAnomaly::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'anomaly_detection_model_id' => AnomalyDetectionModel::factory(),
            'company_id' => Company::factory(),
            'anomaly_score' => fake()->randomFloat(4, 0, 1),
            'severity' => fake()->randomElement(['low', 'medium', 'high', 'critical']),
            'description' => fake()->sentence(),
            'detected_features' => [
                'feature_1' => fake()->randomFloat(2, 0, 100),
                'feature_2' => fake()->randomFloat(2, 0, 100),
            ],
            'baseline_metrics' => [
                'mean' => fake()->randomFloat(2, 0, 100),
                'stddev' => fake()->randomFloat(2, 0, 10),
            ],
            'status' => fake()->randomElement(['new', 'investigating', 'resolved', 'dismissed']),
            'detected_at' => fake()->dateTimeBetween('-30 days', 'now'),
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
