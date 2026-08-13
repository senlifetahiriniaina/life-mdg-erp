<?php

namespace Modules\Helpdesk\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Helpdesk\app\Models\SentimentModel;

class SentimentModelFactory extends Factory
{
    protected $model = SentimentModel::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'name' => fake()->word(),
            'language' => fake()->word(),
            'model_type' => fake()->word(),
            'provider' => fake()->word(),
            'model_identifier' => fake()->word(),
            'accuracy' => fake()->word(),
            'training_samples' => fake()->word(),
            'trained_at' => fake()->word(),
            'deployed_at' => fake()->word(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'hyperparameters' => fake()->word(),
            'notes' => fake()->text(),
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