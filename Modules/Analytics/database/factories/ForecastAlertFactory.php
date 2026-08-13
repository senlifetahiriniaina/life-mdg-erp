<?php

namespace Modules\Analytics\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Analytics\Models\ForecastAlert;

class ForecastAlertFactory extends Factory
{
    protected $model = ForecastAlert::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'alert_type' => fake()->word(),
            'severity' => fake()->word(),
            'message' => fake()->word(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
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