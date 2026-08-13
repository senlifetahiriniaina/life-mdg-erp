<?php

namespace Modules\Achats\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Achats\app\Models\RFQ;

class RFQFactory extends Factory
{
    protected $model = RFQ::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'rfq_number' => fake()->word(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'description' => fake()->text(),
            'required_by_date' => fake()->word(),
            'issued_date' => fake()->word(),
            'deadline_date' => fake()->word(),
            'created_by' => fake()->word(),
            'title' => fake()->word(),
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