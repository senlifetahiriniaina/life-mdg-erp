<?php

namespace Modules\Accounting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\TaxFilingTemplate;

class TaxFilingTemplateFactory extends Factory
{
    protected $model = TaxFilingTemplate::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'tax_jurisdiction_id' => fake()->word(),
            'filing_form_number' => fake()->word(),
            'filing_type' => fake()->word(),
            'field_mappings' => fake()->word(),
            'calculation_rules' => fake()->word(),
            'validation_rules' => fake()->word(),
            'filing_instructions' => fake()->word(),
            'last_updated' => fake()->word(),
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