<?php

namespace Modules\Helpdesk\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Helpdesk\app\Models\ResponseTemplate;

class ResponseTemplateFactory extends Factory
{
    protected $model = ResponseTemplate::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'created_by' => fake()->word(),
            'title' => fake()->word(),
            'content' => fake()->word(),
            'category' => fake()->word(),
            'language' => fake()->word(),
            'tags' => fake()->word(),
            'use_case' => fake()->word(),
            'variables' => fake()->word(),
            'tone' => fake()->word(),
            'avg_resolution_time_minutes' => fake()->word(),
            'avg_satisfaction_rating' => fake()->word(),
            'usage_count' => fake()->word(),
            'positive_feedback_count' => fake()->word(),
            'negative_feedback_count' => fake()->word(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'notes' => fake()->text(),
            'name' => fake()->word(),
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