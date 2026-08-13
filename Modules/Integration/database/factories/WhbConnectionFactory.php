<?php

namespace Modules\Integration\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Integration\Models\WhbConnection;

class WhbConnectionFactory extends Factory
{
    protected $model = WhbConnection::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'local_tenant_id' => fake()->word(),
            'remote_tenant_id' => fake()->word(),
            'remote_server_url' => fake()->word(),
            'remote_tenant_name' => fake()->word(),
            'connection_type' => fake()->word(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'invite_code' => fake()->word(),
            'invite_expires_at' => fake()->word(),
            'shared_secret' => fake()->word(),
            'session_token' => fake()->word(),
            'session_expires_at' => fake()->word(),
            'public_key' => fake()->word(),
            'initiated_by' => fake()->word(),
            'approved_by' => fake()->word(),
            'approved_at' => fake()->word(),
            'last_sync_at' => fake()->word(),
        ];
    }

    /**
     * Indicate model is inactive
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
        ]);
    }

    /**
     * Indicate model is archived
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
        ]);
    }
}