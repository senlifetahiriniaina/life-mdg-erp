<?php

namespace Modules\BI\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\BI\Models\Dashboard;
use Modules\BI\Models\Widget;

class WidgetFactory extends Factory
{
    protected $model = Widget::class;

    /**
     * Define the model's default state.
     *
     * Chantier 19 Lot 5: this was scaffold boilerplate — `dashboard_id`
     * (an unsignedBigInteger FK) and `refresh_interval` (an integer)
     * getting `fake()->word()` (a random string), and `config`/`position`
     * (both cast as `array`) also getting `fake()->word()` — a plain
     * string, not an array, matching the "fake()->word() on a
     * typed/array-cast column" bug pattern already fixed repeatedly
     * elsewhere this session. Also had a stray `name` key, a field Widget
     * has never had in $fillable (silently dropped on create). Widget only
     * had zero real consumers before Chantier 19 Lot 5's WidgetController
     * — rewritten now that it's a genuinely live model, matching the
     * "close the landmine before the next chantier trips it" precedent.
     */
    public function definition(): array
    {
        return [
            'dashboard_id' => Dashboard::factory(),
            'title' => fake()->words(3, true),
            'type' => fake()->randomElement(['kpi_card', 'bar_chart', 'line_chart', 'pie_chart', 'data_table']),
            'config' => ['metric' => fake()->word(), 'period' => 'month'],
            'position' => ['order' => 0, 'w' => 6, 'h' => 1],
            'refresh_interval' => fake()->randomElement([60, 300, 900]),
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