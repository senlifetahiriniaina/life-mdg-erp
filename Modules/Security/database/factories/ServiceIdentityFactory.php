<?php

namespace Modules\Security\Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Security\Models\ServiceIdentity;

class ServiceIdentityFactory extends Factory
{
    protected $model = ServiceIdentity::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'service_name' => fake()->words(2, true) . ' Service',
            'service_type' => fake()->randomElement(['internal_service', 'external_api', 'microservice', 'daemon']),
            'public_key' => fake()->sha256(),
            'private_key_hash' => fake()->sha256(),
            'allowed_permissions' => fake()->randomElements(['read', 'write', 'admin', 'deploy'], 2),
            'resource_restrictions' => ['ip_whitelist' => [fake()->ipv4()]],
            'last_rotated_at' => fake()->dateTime(),
            'expires_at' => fake()->dateTime('+1 year'),
            'is_active' => fake()->boolean(80),
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
        // ServiceIdentity uses SoftDeletes — 'archived' maps to a real
        // deleted_at rather than the nonexistent archived_at column.
        return $this->state(fn (array $attributes) => [
            'deleted_at' => now(),
        ]);
    }
}
