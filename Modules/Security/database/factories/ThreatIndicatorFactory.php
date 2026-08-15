<?php

namespace Modules\Security\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Security\Models\ThreatIndicator;

class ThreatIndicatorFactory extends Factory
{
    protected $model = ThreatIndicator::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'indicator_type' => fake()->randomElement(['ip_address', 'domain', 'hash', 'email', 'user_agent']),
            'indicator_value' => fake()->ipv4(),
            'threat_level' => fake()->randomElement(['low', 'medium', 'high', 'critical']),
            'description' => fake()->text(),
            'source' => fake()->randomElement(['threat_feed', 'manual', 'ids', 'honeypot']),
            'is_whitelisted' => fake()->boolean(),
            'detected_at' => fake()->dateTime(),
            'expires_at' => fake()->dateTime('+30 days'),
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
