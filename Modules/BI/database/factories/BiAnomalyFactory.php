<?php

declare(strict_types=1);

namespace Modules\BI\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\BI\Models\BiAnomaly;

/** @extends Factory<BiAnomaly> */
class BiAnomalyFactory extends Factory
{
    protected $model = BiAnomaly::class;

    public function definition(): array
    {
        $expected = fake()->randomFloat(2, 1000, 10000);
        $deviation = fake()->randomFloat(4, 15, 50);
        $actual = $expected * (1 + $deviation / 100);

        return [
            'entity_type' => 'revenue',
            'entity_id' => null,
            'metric_name' => 'monthly_revenue',
            'detected_at' => now(),
            'anomaly_date' => now()->toDateString(),
            'expected_value' => $expected,
            'actual_value' => $actual,
            'deviation_percent' => $deviation,
            'severity' => 'medium',
            'status' => 'new',
            'description' => null,
            'acknowledged_at' => null,
            'acknowledged_by' => null,
        ];
    }
}
