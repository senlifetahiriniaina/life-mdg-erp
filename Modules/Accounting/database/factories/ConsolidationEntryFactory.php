<?php

namespace Modules\Accounting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\ConsolidationEntry;

class ConsolidationEntryFactory extends Factory
{
    protected $model = ConsolidationEntry::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'consolidation_period_id' => fake()->word(),
            'company_id' => fake()->word(),
            'gl_account_id' => fake()->word(),
            'opening_balance' => fake()->word(),
            'debit_amount' => fake()->word(),
            'credit_amount' => fake()->word(),
            'closing_balance' => fake()->word(),
            'consolidation_adjustment' => fake()->word(),
            'consolidated_amount' => fake()->word(),
            'notes' => fake()->text(),
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