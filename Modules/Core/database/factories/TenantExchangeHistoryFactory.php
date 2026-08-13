<?php

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\TenantExchangeHistory;

class TenantExchangeHistoryFactory extends Factory
{
    protected $model = TenantExchangeHistory::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'exchange_id' => fake()->word(),
            'action' => fake()->word(),
            'actor_tenant_id' => fake()->word(),
            'actor_user_id' => fake()->word(),
            'note' => fake()->word(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
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