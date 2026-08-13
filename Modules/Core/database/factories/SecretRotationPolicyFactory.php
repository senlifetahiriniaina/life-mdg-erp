<?php

namespace Modules\Core\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\app\Models\SecretRotationPolicy;

class SecretRotationPolicyFactory extends Factory
{
    protected $model = SecretRotationPolicy::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'id' => fake()->word(),
            'tenant_id' => fake()->word(),
            'secret_id' => fake()->word(),
            'rotation_interval' => fake()->word(),
            'last_rotation_at' => fake()->word(),
            'next_rotation_at' => fake()->word(),
            'auto_rotate' => fake()->word(),
            'notification_days_before' => fake()->word(),
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