<?php

namespace Modules\CRM\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\CRM\Models\RevenueAnomaly;

class RevenueAnomalyFactory extends Factory
{
    protected $model = RevenueAnomaly::class;

    public function definition(): array
    {
        $detected = $this->faker->numberBetween(100000, 500000);
        $expected = $this->faker->numberBetween(50000, 200000);
        $deviation = (($detected - $expected) / $expected) * 100;

        return [
            'anomaly_type'    => $this->faker->randomElement(['unusual_spike', 'unexpected_drop', 'outlier_value', 'forecast_deviation']),
            'metric_name'     => $this->faker->randomElement(['closed_revenue', 'deal_size', 'pipeline_value', 'win_rate']),
            'detected_value'  => $detected,
            'expected_value'  => $expected,
            'deviation_pct'   => (int) $deviation,
            'severity'        => abs($deviation) > 50 ? 'high' : 'medium',
            'status'          => 'detected',
            'detected_at'     => now(),
        ];
    }
}
