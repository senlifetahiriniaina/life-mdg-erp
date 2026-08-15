<?php

namespace Modules\Integration\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Integration\Models\IntegrationConnector;
use Modules\Integration\Models\SyncLog;

class SyncLogFactory extends Factory
{
    protected $model = SyncLog::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'connector_id' => IntegrationConnector::factory(),
            'tenant_id' => fake()->word(),
            'direction' => fake()->randomElement(['inbound', 'outbound']),
            'status' => fake()->randomElement(['success', 'failed', 'partial']),
            'payload_size' => fake()->numberBetween(100, 10000),
            'records_processed' => fake()->numberBetween(0, 200),
            'records_failed' => fake()->numberBetween(0, 20),
            'error_details' => [],
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