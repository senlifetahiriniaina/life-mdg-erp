<?php

namespace Modules\Security\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Security\Models\SecurityIncident;

class SecurityIncidentFactory extends Factory
{
    protected $model = SecurityIncident::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'company_id' => fake()->word(),
            'incident_type' => fake()->word(),
            'severity' => fake()->word(),
            'description' => fake()->text(),
            'threat_indicators' => fake()->word(),
            'incident_status' => fake()->word(),
            'detected_at' => fake()->word(),
            'investigation_started_at' => fake()->word(),
            'resolved_at' => fake()->word(),
            'resolution_notes' => fake()->word(),
            'affected_resources' => fake()->word(),
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