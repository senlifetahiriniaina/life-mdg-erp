<?php

namespace Modules\BI\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\BI\app\Models\KpiHistory;

class KpiHistoryFactory extends Factory
{
    protected $model = KpiHistory::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
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