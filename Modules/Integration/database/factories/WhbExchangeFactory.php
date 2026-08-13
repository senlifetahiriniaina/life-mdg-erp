<?php

namespace Modules\Integration\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Integration\Models\WhbExchange;

class WhbExchangeFactory extends Factory
{
    protected $model = WhbExchange::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'connection_id' => fake()->word(),
            'direction' => fake()->word(),
            'payload' => fake()->word(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
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