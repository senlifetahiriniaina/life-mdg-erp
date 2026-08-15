<?php

namespace Modules\Integration\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Integration\Models\IntegrationConnector;
use Modules\Integration\Models\WebhookEndpoint;

class WebhookEndpointFactory extends Factory
{
    protected $model = WebhookEndpoint::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'connector_id' => IntegrationConnector::factory(),
            'url' => fake()->url(),
            'method' => fake()->randomElement(['GET', 'POST', 'PUT', 'PATCH', 'DELETE']),
            'headers' => ['Content-Type' => 'application/json'],
            'secret_key' => fake()->word(),
            'retry_attempts' => fake()->numberBetween(0, 5),
            'timeout_seconds' => fake()->numberBetween(5, 120),
            'is_active' => true,
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
        ]);
    }
}