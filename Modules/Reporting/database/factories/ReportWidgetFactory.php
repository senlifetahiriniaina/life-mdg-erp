<?php

namespace Modules\Reporting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Reporting\Models\ReportWidget;

class ReportWidgetFactory extends Factory
{
    protected $model = ReportWidget::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'tenant_id' => fake()->word(),
            'dashboard_id' => fake()->word(),
            'widget_type' => fake()->word(),
            'data_source' => fake()->word(),
            'refresh_interval_seconds' => fake()->word(),
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