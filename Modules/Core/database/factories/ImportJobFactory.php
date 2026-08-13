<?php

namespace Modules\Core\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\app\Models\ImportJob;

class ImportJobFactory extends Factory
{
    protected $model = ImportJob::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'target_entity' => fake()->word(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'total_rows' => fake()->word(),
            'failed_rows' => fake()->word(),
            'name' => fake()->word(),
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