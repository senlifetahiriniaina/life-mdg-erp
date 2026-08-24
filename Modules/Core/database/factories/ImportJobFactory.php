<?php

namespace Modules\Core\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\ImportJob;

/**
 * Chantier 32.1: this was scaffold boilerplate — total_rows/failed_rows set
 * via fake()->word() (a string on an integer column), 'name' (not a real
 * fillable field at all), no user_id/file_path/file_type set despite all 3
 * being required by every real controller/job. Rewritten to match the
 * model's real $fillable/real core_import_jobs columns.
 */
class ImportJobFactory extends Factory
{
    protected $model = ImportJob::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'filename' => fake()->word().'.csv',
            'file_path' => 'imports/'.fake()->uuid().'.csv',
            'file_type' => 'csv',
            'target_entity' => fake()->randomElement(['contact', 'lead', 'product', 'employee', 'supplier', 'invoice']),
            'status' => 'uploaded',
            'total_rows' => 0,
            'processed' => 0,
            'failed' => 0,
        ];
    }

    public function extracted(int $totalRows = 5): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'extracted',
            'total_rows' => $totalRows,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
        ]);
    }
}
