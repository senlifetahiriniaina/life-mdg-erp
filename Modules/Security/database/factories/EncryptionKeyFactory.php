<?php

namespace Modules\Security\Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Security\Models\EncryptionKey;

class EncryptionKeyFactory extends Factory
{
    protected $model = EncryptionKey::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'key_name' => fake()->words(3, true),
            'key_type' => fake()->randomElement(['AES-256-GCM', 'RSA', 'HMAC']),
            'key_usage' => fake()->randomElement(['data_encryption', 'field_encryption', 'signing']),
            'key_status' => fake()->randomElement(['active', 'rotated', 'revoked']),
            'key_material_hash' => fake()->sha256(),
            'vault_reference' => fake()->uuid(),
            'key_length_bits' => fake()->randomElement([128, 256, 2048, 4096]),
            'created_at' => fake()->dateTime(),
            'rotated_at' => fake()->dateTime(),
            'expires_at' => fake()->dateTime('+1 year'),
            'metadata' => ['owner' => fake()->name(), 'purpose' => fake()->word()],
        ];
    }

    /**
     * Indicate model is inactive
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'key_status' => 'revoked',
        ]);
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
