<?php

namespace Modules\Helpdesk\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Helpdesk\Models\ConversationAnalytics;

class ConversationAnalyticsFactory extends Factory
{
    protected $model = ConversationAnalytics::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'company_id' => fake()->word(),
            'ticket_id' => fake()->word(),
            'channel' => fake()->word(),
            'message_count' => fake()->word(),
            'response_count' => fake()->word(),
            'first_response_time_seconds' => fake()->word(),
            'avg_response_time_seconds' => fake()->word(),
            'resolution_time_seconds' => fake()->word(),
            'sentiment_score' => fake()->word(),
            'delivery_rate' => fake()->word(),
            'read_rate' => fake()->word(),
            'click_rate' => fake()->word(),
            'escalations_count' => fake()->word(),
            'transfers_count' => fake()->word(),
            'name' => fake()->word(),
            'title' => fake()->word(),
            'description' => fake()->text(),
            'slug' => fake()->slug(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
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