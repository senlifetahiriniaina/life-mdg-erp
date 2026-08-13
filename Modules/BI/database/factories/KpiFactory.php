<?php

declare(strict_types=1);

namespace Modules\BI\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\BI\Models\Kpi;

/** @extends Factory<Kpi> */
class KpiFactory extends Factory
{
    protected $model = Kpi::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'code' => strtoupper(fake()->unique()->bothify('KPI-####')),
            'metric' => fake()->word(),
            'source_module' => fake()->randomElement(['CRM', 'HR', 'Inventory', 'Accounting']),
            'current_value' => fake()->randomFloat(2, 0, 10000),
            'unit' => fake()->randomElement(['%', '$', 'count']),
        ];
    }
}
