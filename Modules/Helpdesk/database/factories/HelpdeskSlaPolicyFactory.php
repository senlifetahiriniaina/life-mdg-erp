<?php

namespace Modules\Helpdesk\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Helpdesk\Models\HelpdeskSlaPolicy;

class HelpdeskSlaPolicyFactory extends Factory
{
    protected $model = HelpdeskSlaPolicy::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'name' => fake()->word(),
            'priority' => fake()->word(),
            'first_response_hours' => fake()->word(),
            'resolution_hours' => fake()->word(),
            'business_hours_only' => fake()->word(),
            'is_default' => fake()->word(),
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