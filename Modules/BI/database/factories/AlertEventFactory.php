<?php

declare(strict_types=1);

namespace Modules\BI\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\BI\Models\AlertEvent;
use Modules\BI\Models\KpiAlert;

/** @extends Factory<AlertEvent> */
class AlertEventFactory extends Factory
{
    protected $model = AlertEvent::class;

    public function definition(): array
    {
        return [
            'alert_id' => KpiAlert::factory(),
            'triggered_value' => $this->faker->randomFloat(2, 0, 10000),
            'threshold' => 100.0,
            'message' => $this->faker->sentence(),
            'severity' => $this->faker->randomElement(['info', 'warning', 'critical']),
            'acknowledged' => false,
        ];
    }

    public function acknowledged(): static
    {
        return $this->state(fn () => [
            'acknowledged' => true,
            'acknowledged_at' => now(),
        ]);
    }
}
