<?php

namespace Modules\Helpdesk\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Helpdesk\app\Models\TicketComment;

class TicketCommentFactory extends Factory
{
    protected $model = TicketComment::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'ticket_id' => fake()->word(),
            'user_id' => fake()->word(),
            'is_internal' => fake()->word(),
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