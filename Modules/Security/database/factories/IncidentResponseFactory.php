<?php

namespace Modules\Security\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Security\Models\IncidentResponse;
use Modules\Security\Models\SecurityIncident;

class IncidentResponseFactory extends Factory
{
    protected $model = IncidentResponse::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'security_incident_id' => SecurityIncident::factory(),
            'response_type' => fake()->randomElement(['alert', 'block', 'quarantine', 'investigate', 'isolate']),
            'response_status' => fake()->randomElement(['pending', 'completed', 'failed']),
            'response_config' => ['target' => fake()->ipv4()],
            'executed_at' => fake()->dateTime(),
            // NOTE: execution_result is a json column (security_incident_responses)
            // but IncidentResponse::$casts does not declare it as 'array' — passing
            // a raw PHP array here would fail PDO binding. Encode it explicitly so
            // it inserts as a plain JSON string regardless of the missing cast.
            'execution_result' => json_encode([
                'status' => fake()->randomElement(['success', 'failure']),
                'message' => fake()->sentence(),
            ]),
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
        // No archived_at column on this model; kept as a no-op state so
        // existing callers of ->archived() don't break.
        return $this->state(fn (array $attributes) => []);
    }
}
