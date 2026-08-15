<?php

namespace Modules\Integration\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Integration\Models\WhbConnection;
use Modules\Integration\Models\WhbPermission;

class WhbPermissionFactory extends Factory
{
    protected $model = WhbPermission::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'connection_id' => WhbConnection::factory(),
            'data_type' => fake()->randomElement(['invoice', 'contact', 'product', 'order']),
            'can_receive' => fake()->boolean(),
            'can_send' => fake()->boolean(),
            'auto_accept' => fake()->boolean(),
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