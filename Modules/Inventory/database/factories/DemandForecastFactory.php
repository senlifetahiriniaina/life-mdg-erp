<?php

declare(strict_types=1);

namespace Modules\Inventory\Database\Factories;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Inventory\Models\DemandForecast;
use Modules\Inventory\Models\Product;

/** @extends Factory<DemandForecast> */
class DemandForecastFactory extends Factory
{
    protected $model = DemandForecast::class;

    public function definition(): array
    {
        $start = Carbon::now()->startOfMonth()->addMonths(fake()->numberBetween(1, 6));

        return [
            'product_id' => Product::factory(),
            'warehouse_id' => null,
            'period_type' => 'monthly',
            'period_start' => $start,
            'period_end' => $start->copy()->endOfMonth(),
            'forecasted_qty' => fake()->randomFloat(2, 10, 500),
            'actual_qty' => null,
            'confidence' => fake()->randomFloat(2, 50, 99),
            'method' => fake()->randomElement(['moving_average', 'exponential_smoothing', 'seasonal']),
            'metadata' => null,
            'status' => 'draft',
        ];
    }

    public function confirmed(): static
    {
        return $this->state(['status' => 'confirmed']);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => 'expired',
            'actual_qty' => fake()->randomFloat(2, 5, 600),
            'period_start' => Carbon::now()->startOfMonth()->subMonths(fake()->numberBetween(1, 3)),
        ]);
    }
}
