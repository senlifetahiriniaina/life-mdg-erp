<?php

namespace Modules\Achats\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Achats\app\Models\RFQLine;

class RFQLineFactory extends Factory
{
    protected $model = RFQLine::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'rfq_id' => fake()->word(),
            'description' => fake()->text(),
            'quantity' => fake()->numberBetween(1, 100),
            'unit' => fake()->word(),
            'required_date' => fake()->word(),
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