<?php

namespace Modules\BI\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\BI\app\Models\Widget;

class WidgetFactory extends Factory
{
    protected $model = Widget::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'dashboard_id' => fake()->word(),
            'title' => fake()->word(),
            'type' => fake()->word(),
            'config' => fake()->word(),
            'position' => fake()->word(),
            'refresh_interval' => fake()->word(),
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