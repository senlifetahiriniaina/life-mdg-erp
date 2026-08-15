<?php

namespace Modules\Integration\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Integration\Models\Integration;

class IntegrationFactory extends Factory
{
    protected $model = Integration::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fake()->word(),
            'integration_key' => fake()->unique()->word(),
            'name' => fake()->word(),
            'status' => fake()->randomElement(['connected', 'disconnected', 'error']),
            'credentials' => ['api_key' => fake()->uuid(), 'api_secret' => fake()->sha256()],
            'settings' => ['webhook_url' => fake()->url(), 'timeout' => fake()->numberBetween(5, 60)],
            'last_synced_at' => fake()->dateTime(),
            'sync_count' => fake()->numberBetween(0, 500),
            'error_count' => fake()->numberBetween(0, 20),
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