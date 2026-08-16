<?php

namespace Modules\Analytics\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Analytics\Models\ForecastAlert;
use Modules\Analytics\Models\ForecastModel;

class ForecastAlertFactory extends Factory
{
    protected $model = ForecastAlert::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'forecast_model_id' => ForecastModel::factory(),
            'alert_type' => fake()->randomElement(['stockout_risk', 'cashflow_deficit', 'above_threshold']),
            'severity' => fake()->randomElement(['info', 'warning', 'critical']),
            'message' => fake()->sentence(),
            'context' => [
                'predicted_value' => fake()->randomFloat(4, 0, 100000),
                'threshold_value' => fake()->randomFloat(4, 0, 100000),
            ],
            'status' => 'active',
            'triggered_at' => fake()->dateTime(),
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
