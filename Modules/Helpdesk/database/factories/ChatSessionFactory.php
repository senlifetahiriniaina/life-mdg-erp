<?php

namespace Modules\Helpdesk\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Helpdesk\app\Models\ChatSession;

class ChatSessionFactory extends Factory
{
    protected $model = ChatSession::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'visitor_id' => fake()->word(),
            'visitor_name' => fake()->word(),
            'visitor_email' => fake()->word(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'channel' => fake()->word(),
            'assigned_agent_id' => fake()->word(),
            'ticket_id' => fake()->word(),
            'metadata' => fake()->word(),
            'started_at' => fake()->word(),
            'closed_at' => fake()->word(),
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