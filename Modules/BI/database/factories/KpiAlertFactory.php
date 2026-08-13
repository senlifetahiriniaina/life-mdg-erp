<?php

declare(strict_types=1);

namespace Modules\BI\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\BI\Models\KpiAlert;

/** @extends Factory<KpiAlert> */
class KpiAlertFactory extends Factory
{
    protected $model = KpiAlert::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'kpi_id' => null,
            'metric_name' => 'revenue',
            'condition' => 'above',
            'threshold' => 10000,
            'comparison_value' => null,
            'severity' => 'warning',
            'is_active' => true,
            'notification_channels' => ['email'],
            'recipients' => [fake()->safeEmail()],
            'created_by' => null,
        ];
    }
}
