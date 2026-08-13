<?php

namespace Modules\Core\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\app\Models\SyncQueue;

class SyncQueueFactory extends Factory
{
    protected $model = SyncQueue::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'id' => fake()->word(),
            'user_id' => fake()->word(),
            'entity_type' => fake()->word(),
            'entity_id' => fake()->word(),
            'operation' => fake()->word(),
            'payload' => fake()->word(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'conflict_resolution' => fake()->word(),
            'error_message' => fake()->word(),
            'client_timestamp' => fake()->word(),
            'name' => fake()->word(),
            'title' => fake()->word(),
            'description' => fake()->text(),
            'slug' => fake()->slug(),
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