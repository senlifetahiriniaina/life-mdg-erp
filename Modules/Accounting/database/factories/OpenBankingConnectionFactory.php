<?php

namespace Modules\Accounting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\OpenBankingConnection;

class OpenBankingConnectionFactory extends Factory
{
    protected $model = OpenBankingConnection::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'bank_account_id' => fake()->word(),
            'provider' => fake()->word(),
            'access_token' => fake()->word(),
            'refresh_token' => fake()->word(),
            'token_expires_at' => fake()->word(),
            'requisition_id' => fake()->word(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'created_by' => fake()->word(),
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