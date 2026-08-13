<?php

namespace Modules\Security\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Security\Models\KeyRotationLog;

class KeyRotationLogFactory extends Factory
{
    protected $model = KeyRotationLog::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'encryption_key_id' => fake()->word(),
            'rotation_type' => fake()->word(),
            'rotation_status' => fake()->word(),
            'old_key_hash' => fake()->word(),
            'new_key_hash' => fake()->word(),
            'started_at' => fake()->word(),
            'completed_at' => fake()->word(),
            'error_message' => fake()->word(),
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