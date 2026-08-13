<?php

namespace Modules\Settings\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Settings\app\Models\Setting;

class SettingFactory extends Factory
{
    protected $model = Setting::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'tenant_id' => fake()->word(),
            'key' => fake()->word(),
            'value' => fake()->word(),
            'description' => fake()->text(),
            'is_public' => fake()->word(),
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