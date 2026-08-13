<?php

namespace Modules\BI\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\BI\Models\BiDataSource;

class BiDataSourceFactory extends Factory
{
    protected $model = BiDataSource::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'name' => fake()->word(),
            'type' => fake()->word(),
            'connection_config' => fake()->word(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'last_tested_at' => fake()->word(),
            'created_by' => fake()->word(),
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