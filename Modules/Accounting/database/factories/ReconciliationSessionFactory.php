<?php

namespace Modules\Accounting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\ReconciliationSession;

class ReconciliationSessionFactory extends Factory
{
    protected $model = ReconciliationSession::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'bank_account_id' => fake()->word(),
            'period_start' => fake()->word(),
            'period_end' => fake()->word(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'opening_balance' => fake()->word(),
            'closing_balance' => fake()->word(),
            'created_by' => fake()->word(),
            'completed_at' => fake()->word(),
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