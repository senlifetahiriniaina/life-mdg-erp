<?php

namespace Modules\Core\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\app\Models\RateLimitMetrics;

class RateLimitMetricsFactory extends Factory
{
    protected $model = RateLimitMetrics::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'user_id' => fake()->word(),
            'endpoint' => fake()->word(),
            'timestamp' => fake()->word(),
            'ip_address' => fake()->word(),
            'user_agent' => fake()->word(),
            'status_code' => fake()->word(),
            'response_time_ms' => fake()->word(),
            'tenant_id' => fake()->word(),
        ];
    }

    /**
     * Indicate model is inactive
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate model is archived
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'archived_at' => now(),
        ]);
    }
}