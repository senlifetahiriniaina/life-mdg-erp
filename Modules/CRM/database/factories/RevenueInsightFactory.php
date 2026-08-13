<?php

namespace Modules\CRM\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\CRM\Models\RevenueInsight;

class RevenueInsightFactory extends Factory
{
    protected $model = RevenueInsight::class;

    public function definition(): array
    {
        return [
            'insight_type'  => $this->faker->randomElement(['trend', 'anomaly', 'opportunity', 'risk', 'recommendation']),
            'category'      => $this->faker->randomElement(['sales_performance', 'pipeline_health', 'forecast_accuracy', 'team_efficiency']),
            'title'         => $this->faker->sentence(5),
            'description'   => $this->faker->paragraph(),
            'impact_score'  => $this->faker->numberBetween(1, 10),
            'status'        => 'active',
            'insight_generated_at' => now(),
        ];
    }
}
