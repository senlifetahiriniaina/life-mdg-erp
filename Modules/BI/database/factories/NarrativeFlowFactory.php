<?php

namespace Modules\BI\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\BI\app\Models\NarrativeFlow;

class NarrativeFlowFactory extends Factory
{
    protected $model = NarrativeFlow::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'story_id' => fake()->word(),
            'name' => fake()->word(),
            'description' => fake()->text(),
            'flow_config' => fake()->word(),
            'is_active' => true,
            'title' => fake()->word(),
            'slug' => fake()->slug(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'code' => fake()->bothify('??-##'),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'amount' => fake()->randomFloat(2, 0, 1000),
            'quantity' => fake()->numberBetween(1, 100),
            'price' => fake()->randomFloat(2, 0, 1000),
            'cost' => fake()->randomFloat(2, 0, 1000),
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