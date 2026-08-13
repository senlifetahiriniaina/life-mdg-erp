<?php

namespace Modules\Reporting\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Reporting\app\Models\SavedQuery;

class SavedQueryFactory extends Factory
{
    protected $model = SavedQuery::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'tenant_id' => fake()->word(),
            'name' => fake()->word(),
            'description' => fake()->text(),
            'created_by' => fake()->word(),
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