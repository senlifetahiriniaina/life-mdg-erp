<?php

namespace Modules\Setup\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Setup\Models\FieldMapping;
use Modules\Setup\Models\ImportJob;

class FieldMappingFactory extends Factory
{
    protected $model = FieldMapping::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'import_job_id' => ImportJob::factory(),
            'source_field' => fake()->word(),
            'target_field' => fake()->word(),
            'target_table' => fake()->randomElement(['crm_contacts', 'accounting_invoices', 'hr_employees', 'inventory_products', 'sales_orders']),
            'transform_type' => fake()->randomElement(['direct', 'date_format', 'number_format', 'lookup', 'concat', 'split', 'custom']),
            'transform_config' => ['format' => fake()->word()],
            'is_required' => fake()->boolean(),
            'is_ai_suggested' => fake()->boolean(),
            'ai_confidence' => fake()->randomFloat(2, 0, 1),
            'is_confirmed' => fake()->boolean(),
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