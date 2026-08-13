<?php

namespace Modules\Timesheets\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Timesheets\Models\Timesheet;

class TimesheetFactory extends Factory
{
    protected $model = Timesheet::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'tenant_id' => fake()->word(),
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