<?php

namespace Modules\BI\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\BI\Models\BiAlertEvent;

class BiAlertEventFactory extends Factory
{
    protected $model = BiAlertEvent::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'alert_id' => fake()->word(),
            'threshold' => fake()->word(),
            'name' => fake()->word(),
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