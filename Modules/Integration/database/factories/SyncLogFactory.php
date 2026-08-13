<?php

namespace Modules\Integration\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
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
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'records_processed' => fake()->word(),
            'records_failed' => fake()->word(),
            'error_details' => fake()->word(),
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