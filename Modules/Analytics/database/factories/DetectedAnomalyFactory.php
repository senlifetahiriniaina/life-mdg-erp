<?php

namespace Modules\Analytics\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Analytics\app\Models\DetectedAnomaly;

class DetectedAnomalyFactory extends Factory
{
    protected $model = DetectedAnomaly::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'anomaly_detection_model_id' => fake()->word(),
            'severity' => fake()->word(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'detected_at' => fake()->word(),
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