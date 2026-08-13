<?php

namespace Modules\Security\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Security\app\Models\AuthenticationEvent;

class AuthenticationEventFactory extends Factory
{
    protected $model = AuthenticationEvent::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'user_id' => fake()->word(),
            'user_email' => fake()->word(),
            'event_type' => fake()->word(),
            'authentication_method' => fake()->word(),
            'ip_address' => fake()->word(),
            'user_agent' => fake()->word(),
            'device_info' => fake()->word(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'failure_reason' => fake()->word(),
            'trust_score' => fake()->word(),
            'risk_factors' => fake()->word(),
            'authenticated_at' => fake()->word(),
            'created_at' => fake()->word(),
            'updated_at' => fake()->word(),
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