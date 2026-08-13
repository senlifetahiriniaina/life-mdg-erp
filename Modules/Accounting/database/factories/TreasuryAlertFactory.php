<?php

declare(strict_types=1);

namespace Modules\Accounting\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\TreasuryAlert;

/** @extends Factory<TreasuryAlert> */
class TreasuryAlertFactory extends Factory
{
    protected $model = TreasuryAlert::class;

    public function definition(): array
    {
        $type = fake()->randomElement([
            'low_balance', 'high_balance', 'large_outflow', 'negative_forecast',
        ]);

        return [
            'created_by' => User::factory(),
            'name' => ucfirst(str_replace('_', ' ', $type)).' alert',
            'type' => $type,
            'threshold_amount' => fake()->randomFloat(2, 1000, 100000),
            'days_lookahead' => fake()->randomElement([7, 14, 30, 60, 90]),
            'severity' => fake()->randomElement(['warning', 'critical']),
            'is_active' => fake()->boolean(70),
            'notification_channels' => fake()->randomElements(
                ['email', 'slack', 'sms', 'in_app'],
                fake()->numberBetween(1, 3)
            ),
            'last_triggered_at' => fake()->optional(0.4)->dateTimeBetween('-30 days', 'now'),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }

    public function critical(): static
    {
        return $this->state(fn (array $attributes) => ['severity' => 'critical']);
    }

    public function warning(): static
    {
        return $this->state(fn (array $attributes) => ['severity' => 'warning']);
    }
}
