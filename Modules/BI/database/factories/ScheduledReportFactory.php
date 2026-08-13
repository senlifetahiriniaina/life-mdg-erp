<?php

declare(strict_types=1);

namespace Modules\BI\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\BI\Models\ScheduledReport;

/** @extends Factory<ScheduledReport> */
class ScheduledReportFactory extends Factory
{
    protected $model = ScheduledReport::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'report_id' => null,
            'schedule' => $this->faker->randomElement(['daily', 'weekly', 'monthly', 'quarterly']),
            'format' => $this->faker->randomElement(['pdf', 'excel', 'csv']),
            'recipients' => ['admin@example.com'],
            'is_active' => true,
            'next_send_at' => now()->addDay(),
            'send_count' => 0,
        ];
    }

    public function due(): static
    {
        return $this->state(fn () => ['next_send_at' => now()->subMinute()]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
