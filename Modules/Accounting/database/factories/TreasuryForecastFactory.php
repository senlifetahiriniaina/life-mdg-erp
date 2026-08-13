<?php

declare(strict_types=1);

namespace Modules\Accounting\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\TreasuryForecast;

/** @extends Factory<TreasuryForecast> */
class TreasuryForecastFactory extends Factory
{
    protected $model = TreasuryForecast::class;

    public function definition(): array
    {
        $opening = fake()->randomFloat(2, 5000, 500000);
        $inflows = fake()->randomFloat(2, 1000, 100000);
        $outflows = fake()->randomFloat(2, 500, 80000);

        return [
            'name' => fake()->words(3, true).' forecast',
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'status' => 'draft',
            'opening_balance' => $opening,
            'total_inflows' => $inflows,
            'total_outflows' => $outflows,
            'closing_balance' => $opening + $inflows - $outflows,
            'currency' => 'USD',
            'notes' => fake()->optional()->sentence(),
            'created_by' => User::factory(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'draft']);
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'active']);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'archived']);
    }
}
