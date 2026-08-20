<?php

namespace Modules\Reporting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Reporting\Models\ReportWidget;

class ReportWidgetFactory extends Factory
{
    protected $model = ReportWidget::class;

    /**
     * Define the model's default state.
     *
     * Chantier 19 (Lot 5): scaffold boilerplate — fake()->word() (a string)
     * into the unsignedBigInteger `dashboard_id` FK and the integer
     * `refresh_interval_seconds` column, plus a `name` field that isn't in
     * ReportWidget's real $fillable at all (the real column is `title`) —
     * confirmed zero real consumers anywhere in the app, but rewritten to
     * match the model's real schema anyway, closing the landmine before a
     * future caller trips it (same precedent as this session's many other
     * zero-consumer-but-still-fixed factory rewrites).
     */
    public function definition(): array
    {
        return [
            'tenant_id'                => fake()->numberBetween(1, 1000),
            'dashboard_id'             => \Modules\Reporting\Models\Dashboard::factory(),
            'widget_type'              => fake()->randomElement(['kpi', 'chart', 'table']),
            'title'                    => fake()->words(2, true),
            'data_source'              => ['module' => 'Sales', 'query' => 'kpi_revenue_month'],
            'config'                   => [],
            'position_x'               => 0,
            'position_y'               => 0,
            'width'                    => 1,
            'height'                   => 1,
            'refresh_interval_seconds' => 300,
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