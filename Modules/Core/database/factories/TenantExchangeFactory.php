<?php

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\TenantExchange;

class TenantExchangeFactory extends Factory
{
    protected $model = TenantExchange::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'source_tenant_id' => fake()->word(),
            'target_tenant_id' => fake()->word(),
            'exchange_type' => fake()->word(),
            'payload' => fake()->word(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'message' => fake()->word(),
            'rejection_reason' => fake()->word(),
            'expires_at' => fake()->word(),
            'accepted_at' => fake()->word(),
            'created_by_user_id' => fake()->word(),
            'accepted_by_user_id' => fake()->word(),
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