<?php

namespace Modules\Projects\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Projects\Models\Milestone;

class MilestoneFactory extends Factory
{
    protected $model = Milestone::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'project_id' => fake()->word(),
            'name' => fake()->word(),
            'due_date' => fake()->dateTime(),
            'is_reached' => fake()->word(),
            'reached_at' => fake()->word(),
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