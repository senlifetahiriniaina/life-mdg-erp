<?php

declare(strict_types=1);

namespace Modules\Accounting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\Budget;
use Modules\Accounting\Models\BudgetAlert;

/** @extends Factory<BudgetAlert> */
class BudgetAlertFactory extends Factory
{
    protected $model = BudgetAlert::class;

    public function definition(): array
    {
        return [
            'budget_id' => Budget::factory(),
            'budget_line_id' => null,
            'alert_type' => fake()->randomElement(['over_budget', 'approaching_limit', 'variance']),
            'status' => 'triggered',
            'threshold_percent' => 90,
            'current_variance_percent' => fake()->randomFloat(2, 90, 150),
            'triggered_at' => now(),
        ];
    }
}
