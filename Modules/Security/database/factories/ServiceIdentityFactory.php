<?php

namespace Modules\Security\Database\Factories;

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
                        'company_id' => fake()->word(),
            'service_name' => fake()->word(),
            'service_type' => fake()->word(),
            'public_key' => fake()->word(),
            'private_key_hash' => fake()->word(),
            'allowed_permissions' => fake()->word(),
            'resource_restrictions' => fake()->word(),
            'last_rotated_at' => fake()->word(),
            'expires_at' => fake()->word(),
            'is_active' => true,
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