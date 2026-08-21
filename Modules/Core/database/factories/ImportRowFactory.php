<?php

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\ImportJob;
use Modules\Core\Models\ImportRow;

/**
 * Chantier 32.1: this was scaffold boilerplate — import_job_id/row_index/
 * created_record_id set via fake()->word() (strings on integer/FK columns),
 * raw_data/mapped_data set to plain strings instead of arrays despite both
 * being 'array'-cast columns. Rewritten to match the model's real
 * $fillable/casts.
 */
class ImportRowFactory extends Factory
{
    protected $model = ImportRow::class;

    public function definition(): array
    {
        return [
            'import_job_id' => ImportJob::factory(),
            'row_index' => fake()->numberBetween(0, 100),
            'raw_data' => ['first_name' => fake()->firstName(), 'last_name' => fake()->lastName(), 'email' => fake()->safeEmail()],
            'mapped_data' => null,
            'status' => 'pending',
            'created_record_id' => null,
            'error_message' => null,
        ];
    }

    public function mapped(): static
    {
        return $this->state(fn (array $attributes) => [
            'mapped_data' => $attributes['raw_data'] ?? [],
        ]);
    }

    public function failed(string $message = 'Import failed'): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'error_message' => $message,
        ]);
    }
}
