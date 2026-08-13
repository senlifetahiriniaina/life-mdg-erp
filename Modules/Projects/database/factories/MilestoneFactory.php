<?php

namespace Modules\Projects\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Projects\app\Models\Milestone;

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