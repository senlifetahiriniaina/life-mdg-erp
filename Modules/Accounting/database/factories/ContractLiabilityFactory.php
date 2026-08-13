<?php

namespace Modules\Accounting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\ContractLiability;

class ContractLiabilityFactory extends Factory
{
    protected $model = ContractLiability::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'revenue_contract_id' => fake()->word(),
            'liability_amount' => fake()->word(),
            'recognized_amount' => fake()->word(),
            'remaining_amount' => fake()->word(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'deferred_revenue_account_id' => fake()->word(),
            'expected_recognition_date' => fake()->word(),
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