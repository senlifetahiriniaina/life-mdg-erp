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
            'integration_key' => fake()->word(),
            'name' => fake()->word(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'credentials' => fake()->word(),
            'settings' => fake()->word(),
            'last_synced_at' => fake()->word(),
            'sync_count' => fake()->word(),
            'error_count' => fake()->word(),
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