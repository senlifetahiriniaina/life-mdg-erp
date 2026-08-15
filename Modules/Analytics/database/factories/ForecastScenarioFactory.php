<?php

namespace Modules\Analytics\Database\Factories;

use App\Models\User;
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
            'tenant_id' => fake()->numberBetween(1, 100),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'base_model_id' => ForecastModel::factory(),
            'assumptions' => [
                'growth_rate' => fake()->randomFloat(2, -10, 20),
                'price_increase' => fake()->randomFloat(2, 0, 10),
            ],
            'results' => [
                'predictions' => [],
            ],
            'created_by' => User::factory(),
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
