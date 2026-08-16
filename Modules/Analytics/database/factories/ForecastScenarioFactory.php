<?php

namespace Modules\Analytics\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Analytics\Models\ForecastModel;
use Modules\Analytics\Models\ForecastScenario;

class ForecastScenarioFactory extends Factory
{
    protected $model = ForecastScenario::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'forecast_model_id' => ForecastModel::factory(),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'assumptions' => [
                'growth_rate' => fake()->randomFloat(2, -10, 20),
                'price_increase' => fake()->randomFloat(2, 0, 10),
            ],
            'results' => [
                'predictions' => [],
            ],
            'status' => 'draft',
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
