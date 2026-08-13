<?php

namespace Modules\Integration\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
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
                        'connector_id' => fake()->word(),
            'url' => fake()->url(),
            'method' => fake()->word(),
            'headers' => fake()->word(),
            'secret_key' => fake()->word(),
            'retry_attempts' => fake()->word(),
            'timeout_seconds' => fake()->word(),
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