<?php

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\SessionSecurityEvent;

class SessionSecurityEventFactory extends Factory
{
    protected $model = SessionSecurityEvent::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'id' => fake()->word(),
            'session_id' => fake()->word(),
            'user_id' => fake()->word(),
            'event_type' => fake()->word(),
            'ip_address' => fake()->word(),
            'old_fingerprint' => fake()->word(),
            'new_fingerprint' => fake()->word(),
            'reason' => fake()->word(),
            'severity' => fake()->word(),
            'action_taken' => fake()->word(),
            'created_at' => fake()->word(),
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