<?php

namespace Modules\Integration\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Integration\Models\Integration;
use Modules\Integration\Models\IntegrationSyncLog;

class IntegrationSyncLogFactory extends Factory
{
    protected $model = IntegrationSyncLog::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'integration_id' => Integration::factory(),
            'direction' => fake()->randomElement(['in', 'out', 'both']),
            'status' => fake()->randomElement(['running', 'success', 'error']),
            'records_synced' => fake()->numberBetween(0, 200),
            'errors' => [],
            'started_at' => fake()->dateTime(),
            'completed_at' => fake()->dateTime(),
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