<?php

namespace Modules\Core\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\app\Models\CsrfToken;

class CsrfTokenFactory extends Factory
{
    protected $model = CsrfToken::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'id' => fake()->word(),
            'user_id' => fake()->word(),
            'token_hash' => fake()->word(),
            'action' => fake()->word(),
            'scope' => fake()->word(),
            'ip_address' => fake()->word(),
            'user_agent_hash' => fake()->word(),
            'created_at' => fake()->word(),
            'expires_at' => fake()->word(),
            'last_verified_at' => fake()->word(),
            'rotation_count' => fake()->word(),
            'revoked_at' => fake()->word(),
            'tenant_id' => fake()->word(),
            'metadata' => fake()->word(),
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