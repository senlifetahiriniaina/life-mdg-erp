<?php

namespace Modules\Reporting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Reporting\Models\ReportShare;

class ReportShareFactory extends Factory
{
    protected $model = ReportShare::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'report_id' => fake()->word(),
            'shared_with_user_id' => fake()->word(),
            'permission' => fake()->word(),
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