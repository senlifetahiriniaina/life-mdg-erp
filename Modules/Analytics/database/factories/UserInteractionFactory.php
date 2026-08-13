<?php

namespace Modules\Analytics\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Analytics\app\Models\UserInteraction;

class UserInteractionFactory extends Factory
{
    protected $model = UserInteraction::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'company_id' => fake()->word(),
            'user_type' => fake()->word(),
            'user_id' => fake()->word(),
            'interacted_item_type' => fake()->word(),
            'interacted_item_id' => fake()->word(),
            'interaction_type' => fake()->word(),
            'engagement_score' => fake()->word(),
            'context' => fake()->word(),
            'interacted_at' => fake()->word(),
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