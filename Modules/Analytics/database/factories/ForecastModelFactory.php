<?php

namespace Modules\Analytics\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Analytics\app\Models\ForecastModel;

class ForecastModelFactory extends Factory
{
    protected $model = ForecastModel::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'tenant_id' => fake()->word(),
            'name' => fake()->word(),
            'module' => fake()->word(),
            'entity_type' => fake()->word(),
            'entity_id' => fake()->word(),
            'algorithm' => fake()->word(),
            'horizon_days' => fake()->word(),
            'confidence_level' => fake()->word(),
            'last_trained_at' => fake()->word(),
            'next_retrain_at' => fake()->word(),
            'is_active' => true,
            'config' => fake()->word(),
            'title' => fake()->word(),
            'description' => fake()->text(),
            'slug' => fake()->slug(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'code' => fake()->bothify('??-##'),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'amount' => fake()->randomFloat(2, 0, 1000),
            'quantity' => fake()->numberBetween(1, 100),
            'price' => fake()->randomFloat(2, 0, 1000),
            'cost' => fake()->randomFloat(2, 0, 1000),
            'notes' => fake()->text(),
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
            'archived_at' => now(),
        ]);
    }
}