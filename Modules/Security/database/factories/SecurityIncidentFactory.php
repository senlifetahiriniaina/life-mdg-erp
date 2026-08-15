<?php

namespace Modules\Security\Database\Factories;

use App\Models\Company;
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
            'company_id' => Company::factory(),
            'incident_type' => fake()->randomElement(['intrusion_attempt', 'data_breach', 'policy_violation', 'anomaly']),
            'severity' => fake()->randomElement(['low', 'medium', 'high', 'critical']),
            'description' => fake()->text(),
            'threat_indicators' => fake()->words(3),
            'incident_status' => fake()->randomElement(['open', 'investigating', 'resolved']),
            'detected_at' => fake()->dateTime(),
            'investigation_started_at' => fake()->dateTime(),
            'resolved_at' => fake()->dateTime(),
            'resolution_notes' => fake()->sentence(),
            'affected_resources' => fake()->words(3),
        ];
    }

    /**
     * Indicate model is inactive
     */
    public function inactive(): static
    {
        // No is_active column on this model; kept as a no-op state so
        // existing callers of ->inactive() don't break.
        return $this->state(fn (array $attributes) => []);
    }

    /**
     * Indicate model is archived
     */
    public function archived(): static
    {
        // SecurityIncident uses SoftDeletes — 'archived' maps to a real
        // deleted_at, unlike the other Security factories in this file.
        return $this->state(fn (array $attributes) => [
            'deleted_at' => now(),
        ]);
    }
}
