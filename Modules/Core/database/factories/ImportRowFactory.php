<?php

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\ImportRow;

class ImportRowFactory extends Factory
{
    protected $model = ImportRow::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'import_job_id' => fake()->word(),
            'row_index' => fake()->word(),
            'raw_data' => fake()->word(),
            'mapped_data' => fake()->word(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'created_record_id' => fake()->word(),
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