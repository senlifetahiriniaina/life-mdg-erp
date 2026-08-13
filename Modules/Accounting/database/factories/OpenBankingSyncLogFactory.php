<?php

namespace Modules\Accounting\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\app\Models\OpenBankingSyncLog;

class OpenBankingSyncLogFactory extends Factory
{
    protected $model = OpenBankingSyncLog::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'connection_id' => fake()->word(),
            'synced_at' => fake()->word(),
            'transactions_fetched' => fake()->word(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'error_message' => fake()->word(),
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