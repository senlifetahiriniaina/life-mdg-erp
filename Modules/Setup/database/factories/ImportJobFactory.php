<?php

namespace Modules\Setup\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Setup\Models\ImportJob;

class ImportJobFactory extends Factory
{
    protected $model = ImportJob::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'tenant_id' => fake()->word(),
            'name' => fake()->word(),
            'source_type' => fake()->word(),
            'source_file_path' => fake()->word(),
            'source_db_driver' => fake()->word(),
            'source_db_config' => fake()->word(),
            'target_module' => fake()->word(),
            'target_entity' => fake()->word(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'total_rows' => fake()->word(),
            'imported_rows' => fake()->word(),
            'failed_rows' => fake()->word(),
            'error_summary' => fake()->word(),
            'ai_mapping_used' => fake()->word(),
            'ai_mapping_confidence' => fake()->word(),
            'created_by' => fake()->word(),
            'started_at' => fake()->word(),
            'completed_at' => fake()->word(),
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