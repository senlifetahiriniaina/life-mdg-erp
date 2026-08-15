<?php

namespace Modules\Security\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Security\Models\EncryptionKey;
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
            'encryption_key_id' => EncryptionKey::factory(),
            'rotation_type' => fake()->randomElement(['scheduled', 'emergency', 'requested']),
            'rotation_status' => fake()->randomElement(['in_progress', 'completed', 'failed']),
            'old_key_hash' => fake()->sha256(),
            'new_key_hash' => fake()->sha256(),
            'started_at' => fake()->dateTime(),
            'completed_at' => fake()->dateTime(),
            'error_message' => fake()->sentence(),
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
