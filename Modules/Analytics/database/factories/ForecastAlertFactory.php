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
            'tenant_id' => fake()->numberBetween(1, 100),
            'model_id' => ForecastModel::factory(),
            'alert_type' => fake()->randomElement(['stockout_risk', 'cashflow_deficit', 'above_threshold']),
            'severity' => fake()->randomElement(['info', 'warning', 'critical']),
            'title' => fake()->sentence(4),
            'message' => fake()->sentence(),
            'predicted_date' => fake()->date(),
            'predicted_value' => fake()->randomFloat(4, 0, 100000),
            'threshold_value' => fake()->randomFloat(4, 0, 100000),
            'is_acknowledged' => fake()->boolean(),
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
