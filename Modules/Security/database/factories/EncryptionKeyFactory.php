<?php

namespace Modules\Security\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Security\app\Models\EncryptionKey;

class EncryptionKeyFactory extends Factory
{
    protected $model = EncryptionKey::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'company_id' => fake()->word(),
            'key_name' => fake()->word(),
            'key_type' => fake()->word(),
            'key_usage' => fake()->word(),
            'key_status' => fake()->word(),
            'key_material_hash' => fake()->word(),
            'vault_reference' => fake()->word(),
            'key_length_bits' => fake()->word(),
            'created_at' => fake()->word(),
            'rotated_at' => fake()->word(),
            'expires_at' => fake()->word(),
            'metadata' => fake()->word(),
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