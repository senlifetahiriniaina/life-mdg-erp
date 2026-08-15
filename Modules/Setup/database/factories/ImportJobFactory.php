<?php

namespace Modules\Setup\Database\Factories;

use App\Models\User;
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
            'tenant_id' => fake()->numberBetween(1, 500),
            'name' => fake()->words(3, true),
            'source_type' => fake()->randomElement(['excel', 'csv', 'pdf', 'database']),
            'source_file_path' => fake()->word() . '.csv',
            'source_db_driver' => fake()->randomElement(['mysql', 'pgsql', 'sqlsrv', 'sqlite']),
            'source_db_config' => ['host' => fake()->ipv4(), 'database' => fake()->word()],
            'target_module' => fake()->randomElement(['CRM', 'Accounting', 'HR', 'Inventory', 'Sales']),
            'target_entity' => fake()->randomElement(['contacts', 'invoices', 'employees', 'products', 'orders']),
            'status' => fake()->randomElement(['pending', 'analyzing', 'mapping', 'importing', 'completed', 'failed']),
            'total_rows' => fake()->numberBetween(0, 1000),
            'imported_rows' => fake()->numberBetween(0, 1000),
            'failed_rows' => fake()->numberBetween(0, 50),
            'error_summary' => ['message' => fake()->sentence()],
            'ai_mapping_used' => fake()->boolean(),
            'ai_mapping_confidence' => fake()->randomFloat(2, 0, 1),
            'created_by' => User::factory(),
            'started_at' => fake()->dateTime(),
            'completed_at' => fake()->dateTime(),
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