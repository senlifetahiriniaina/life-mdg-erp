<?php

namespace Modules\Helpdesk\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Helpdesk\Models\ResponseCustomization;

class ResponseCustomizationFactory extends Factory
{
    protected $model = ResponseCustomization::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'agent_id' => fake()->word(),
            'template_id' => fake()->word(),
            'preferred_variation' => fake()->word(),
            'tone_preference' => fake()->word(),
            'frequent_modifications' => fake()->word(),
            'customization_score' => fake()->word(),
            'times_used' => fake()->word(),
            'times_modified' => fake()->word(),
            'avg_satisfaction_with_variant' => fake()->word(),
            'has_custom_variant' => fake()->word(),
            'custom_variant_id' => fake()->word(),
            'learning_data' => fake()->word(),
            'is_learning_enabled' => fake()->word(),
            'last_used_at' => fake()->word(),
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