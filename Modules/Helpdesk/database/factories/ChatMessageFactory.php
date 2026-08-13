<?php

namespace Modules\Helpdesk\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Helpdesk\app\Models\ChatMessage;

class ChatMessageFactory extends Factory
{
    protected $model = ChatMessage::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'chat_session_id' => fake()->word(),
            'sender_type' => fake()->word(),
            'sender_id' => fake()->word(),
            'message' => fake()->word(),
            'type' => fake()->word(),
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