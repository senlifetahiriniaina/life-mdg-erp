<?php

namespace Modules\BI\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\BI\app\Models\StoryAnalytics;

class StoryAnalyticsFactory extends Factory
{
    protected $model = StoryAnalytics::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'story_id' => fake()->word(),
            'total_views' => fake()->word(),
            'unique_viewers' => fake()->word(),
            'total_slide_views' => fake()->word(),
            'avg_time_per_slide' => fake()->word(),
            'completion_rate' => fake()->word(),
            'shares_count' => fake()->word(),
            'interactions_count' => fake()->word(),
            'last_viewed_at' => fake()->word(),
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