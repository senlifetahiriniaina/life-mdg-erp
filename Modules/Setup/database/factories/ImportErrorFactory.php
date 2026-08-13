<?php

namespace Modules\Setup\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Setup\Models\ImportError;

class ImportErrorFactory extends Factory
{
    protected $model = ImportError::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'import_job_id' => fake()->word(),
            'row_number' => fake()->word(),
            'error_type' => fake()->word(),
            'error_message' => fake()->word(),
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