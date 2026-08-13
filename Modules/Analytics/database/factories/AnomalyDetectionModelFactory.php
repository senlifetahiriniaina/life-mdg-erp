<?php

namespace Modules\Analytics\Database\Factories;

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
            'algorithm' => fake()->word(),
            'name' => fake()->word(),
            'is_active' => true,
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