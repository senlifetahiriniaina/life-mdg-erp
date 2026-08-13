<?php

namespace Modules\Projects\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Projects\app\Models\BudgetLine;

class BudgetLineFactory extends Factory
{
    protected $model = BudgetLine::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'description' => fake()->text(),
            'category' => fake()->word(),
            'actual_amount' => fake()->word(),
            'notes' => fake()->text(),
            'amount' => fake()->randomFloat(2, 0, 1000),
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