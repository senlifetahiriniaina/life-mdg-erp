<?php

namespace Modules\Settings\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Settings\app\Models\SettingGroup;

class SettingGroupFactory extends Factory
{
    protected $model = SettingGroup::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'name' => fake()->word(),
            'description' => fake()->text(),
            'sort_order' => fake()->word(),
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