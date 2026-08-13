<?php

namespace Modules\Core\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\app\Models\SessionEnhanced;

class SessionEnhancedFactory extends Factory
{
    protected $model = SessionEnhanced::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'id' => fake()->word(),
            'user_id' => fake()->word(),
            'ip_address' => fake()->word(),
            'user_agent_hash' => fake()->word(),
            'device_fingerprint' => fake()->word(),
            'browser_fingerprint' => fake()->word(),
            'device_type' => fake()->word(),
            'created_at' => fake()->word(),
            'last_activity_at' => fake()->word(),
            'expires_at' => fake()->word(),
            'fingerprint_checked_at' => fake()->word(),
            'regeneration_count' => fake()->word(),
            'concurrent_session_number' => fake()->word(),
            'suspicious_activity_count' => fake()->word(),
            'tenant_id' => fake()->word(),
            'name' => fake()->word(),
            'title' => fake()->word(),
            'description' => fake()->text(),
            'slug' => fake()->slug(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'code' => fake()->bothify('??-##'),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'amount' => fake()->randomFloat(2, 0, 1000),
            'quantity' => fake()->numberBetween(1, 100),
            'price' => fake()->randomFloat(2, 0, 1000),
            'cost' => fake()->randomFloat(2, 0, 1000),
            'is_active' => true,
            'notes' => fake()->text(),
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