<?php

namespace Modules\Accounting\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\app\Models\IntercompanyClearance;

class IntercompanyClearanceFactory extends Factory
{
    protected $model = IntercompanyClearance::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'sending_company_id' => fake()->word(),
            'receiving_company_id' => fake()->word(),
            'transaction_date' => fake()->word(),
            'transaction_type' => fake()->word(),
            'amount' => fake()->randomFloat(2, 0, 1000),
            'currency' => fake()->word(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'sending_gl_account_id' => fake()->word(),
            'receiving_gl_account_id' => fake()->word(),
            'description' => fake()->text(),
            'due_date' => fake()->dateTime(),
            'cleared_at' => fake()->word(),
            'documents' => fake()->word(),
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