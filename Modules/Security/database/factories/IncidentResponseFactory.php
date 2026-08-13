<?php

namespace Modules\Security\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Security\app\Models\IncidentResponse;

class IncidentResponseFactory extends Factory
{
    protected $model = IncidentResponse::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'security_incident_id' => fake()->word(),
            'response_type' => fake()->word(),
            'response_status' => fake()->word(),
            'response_config' => fake()->word(),
            'executed_at' => fake()->word(),
            'execution_result' => fake()->word(),
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