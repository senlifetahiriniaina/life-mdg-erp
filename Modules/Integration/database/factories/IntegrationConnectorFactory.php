<?php

namespace Modules\Integration\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Integration\Models\IntegrationConnector;

class IntegrationConnectorFactory extends Factory
{
    protected $model = IntegrationConnector::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            // Legacy NOT NULL column from the original migration, superseded by
            // `provider_type` in the model's real $fillable but never made nullable —
            // still required by the schema, so it must be populated on insert.
            'connector_type' => fake()->randomElement(['crm', 'accounting', 'ecommerce', 'payment', 'shipping']),
            'tenant_id' => (string) fake()->numberBetween(1, 1000),
            'name' => fake()->company() . ' Connector',
            'config' => ['api_key' => fake()->uuid(), 'endpoint' => fake()->url()],
            'status' => fake()->randomElement(['active', 'inactive']),
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