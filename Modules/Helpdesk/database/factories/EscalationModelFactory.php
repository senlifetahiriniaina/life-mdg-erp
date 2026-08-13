<?php

namespace Modules\Helpdesk\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Helpdesk\Models\EscalationModel;

class EscalationModelFactory extends Factory
{
    protected $model = EscalationModel::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'name' => fake()->word(),
            'model_type' => fake()->word(),
            'provider' => fake()->word(),
            'model_identifier' => fake()->word(),
            'precision' => fake()->word(),
            'recall' => fake()->word(),
            'f1_score' => fake()->word(),
            'training_samples' => fake()->word(),
            'trained_at' => fake()->word(),
            'deployed_at' => fake()->word(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'feature_importance' => fake()->word(),
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