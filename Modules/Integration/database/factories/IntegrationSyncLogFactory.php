<?php

namespace Modules\Integration\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Integration\app\Models\IntegrationSyncLog;

class IntegrationSyncLogFactory extends Factory
{
    protected $model = IntegrationSyncLog::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'integration_id' => fake()->word(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'started_at' => fake()->word(),
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