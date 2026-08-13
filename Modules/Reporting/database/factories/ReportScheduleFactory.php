<?php

namespace Modules\Reporting\database\factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Reporting\app\Models\ReportSchedule;

class ReportScheduleFactory extends Factory
{
    protected $model = ReportSchedule::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'tenant_id' => fake()->word(),
            'name' => fake()->word(),
            'cron_expression' => fake()->word(),
            'recipients' => fake()->word(),
            'last_run_at' => fake()->word(),
            'next_run_at' => fake()->word(),
            'is_active' => true,
        ];
    }

    /**
     * Indicate model is inactive
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
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