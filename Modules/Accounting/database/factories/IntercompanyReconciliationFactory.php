<?php

namespace Modules\Accounting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\IntercompanyReconciliation;

class IntercompanyReconciliationFactory extends Factory
{
    protected $model = IntercompanyReconciliation::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'company_a_id' => fake()->word(),
            'company_b_id' => fake()->word(),
            'reconciliation_date' => fake()->word(),
            'company_a_balance' => fake()->word(),
            'company_b_balance' => fake()->word(),
            'difference' => fake()->word(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'reconciliation_notes' => fake()->word(),
            'reconciled_at' => fake()->word(),
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