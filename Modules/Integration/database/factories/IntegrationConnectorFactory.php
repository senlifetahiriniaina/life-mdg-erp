<?php

namespace Modules\Integration\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Integration\app\Models\IntegrationConnector;

class IntegrationConnectorFactory extends Factory
{
    protected $model = IntegrationConnector::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'tenant_id' => fake()->word(),
            'name' => fake()->word(),
            'config' => fake()->word(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
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