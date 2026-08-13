<?php

namespace Modules\Accounting\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\app\Models\ConsolidationElimination;

class ConsolidationEliminationFactory extends Factory
{
    protected $model = ConsolidationElimination::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'consolidation_hierarchy_id' => fake()->word(),
            'consolidation_period_id' => fake()->word(),
            'elimination_type' => fake()->word(),
            'gl_account_id' => fake()->word(),
            'debit_amount' => fake()->word(),
            'credit_amount' => fake()->word(),
            'description' => fake()->text(),
            'calculation_method' => fake()->word(),
            'is_manual' => fake()->word(),
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