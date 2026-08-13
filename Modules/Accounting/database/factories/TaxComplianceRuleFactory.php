<?php

namespace Modules\Accounting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\TaxComplianceRule;

class TaxComplianceRuleFactory extends Factory
{
    protected $model = TaxComplianceRule::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'tax_jurisdiction_id' => fake()->word(),
            'rule_name' => fake()->word(),
            'rule_type' => fake()->word(),
            'rule_conditions' => fake()->word(),
            'rule_actions' => fake()->word(),
            'description' => fake()->text(),
            'requires_documentation' => fake()->word(),
            'effective_from' => fake()->word(),
            'effective_to' => fake()->word(),
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