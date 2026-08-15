<?php

namespace Modules\Setup\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Setup\Models\ImportError;
use Modules\Setup\Models\ImportJob;

class ImportErrorFactory extends Factory
{
    protected $model = ImportError::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'import_job_id' => ImportJob::factory(),
            'row_number' => fake()->numberBetween(1, 500),
            'error_type' => fake()->randomElement(['validation', 'duplicate', 'missing_required', 'format_mismatch', 'constraint_violation']),
            'error_message' => fake()->sentence(),
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