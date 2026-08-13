<?php

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\DDoSIncident;

class DDoSIncidentFactory extends Factory
{
    protected $model = DDoSIncident::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'ip_address' => fake()->word(),
            'endpoint' => fake()->word(),
            'risk_level' => fake()->word(),
            'reason' => fake()->word(),
            'detected_at' => fake()->word(),
            'blocked_until' => fake()->word(),
            'metrics' => fake()->word(),
            'request_count' => fake()->word(),
            'requests_per_second' => fake()->word(),
            'attack_signature' => fake()->word(),
            'auto_blocked' => fake()->word(),
            'response_action' => fake()->word(),
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