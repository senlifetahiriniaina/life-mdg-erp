<?php

namespace Modules\Analytics\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Analytics\Models\ForecastModel;

class ForecastModelFactory extends Factory
{
    protected $model = ForecastModel::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fake()->numberBetween(1, 100),
            'name' => fake()->words(3, true),
            'module' => fake()->randomElement(['inventory', 'cashflow', 'demand', 'revenue', 'hr', 'production']),
            'entity_type' => fake()->word(),
            'entity_id' => fake()->numberBetween(1, 1000),
            'algorithm' => fake()->randomElement(['linear_regression', 'moving_average', 'exponential_smoothing', 'ai_claude']),
            'horizon_days' => fake()->randomElement([30, 60, 90, 180]),
            'confidence_level' => fake()->randomFloat(4, 0.8, 0.99),
            'last_trained_at' => fake()->dateTimeBetween('-60 days', 'now'),
            'next_retrain_at' => fake()->dateTimeBetween('now', '+30 days'),
            'is_active' => fake()->boolean(80),
            'config' => [
                'seasonality' => fake()->randomElement(['none', 'weekly', 'monthly']),
                'window' => fake()->numberBetween(7, 90),
            ],
        ];
    }

    /**
     * Indicate model is inactive
     */
    public function inactive(): static
    {
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
