<?php

namespace Modules\BI\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\BI\Models\StorySlide;

class StorySlideFactory extends Factory
{
    protected $model = StorySlide::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'story_id' => fake()->word(),
            'slide_number' => fake()->word(),
            'title' => fake()->word(),
            'narrative_text' => fake()->word(),
            'visualization_config' => fake()->word(),
            'interaction_rules' => fake()->word(),
            'transition_type' => fake()->word(),
            'transition_duration' => fake()->word(),
            'layout' => fake()->word(),
            'name' => fake()->word(),
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