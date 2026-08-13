<?php

namespace Modules\Analytics\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Analytics\app\Models\MLModelVersion;

class MLModelVersionFactory extends Factory
{
    protected $model = MLModelVersion::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'ml_model_id' => fake()->word(),
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