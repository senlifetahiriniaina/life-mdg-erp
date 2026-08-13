<?php

declare(strict_types=1);

namespace Modules\CRM\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\CRM\Models\Forecast;

class ForecastFactory extends Factory
{
    protected $model = Forecast::class;

    public function definition(): array
    {
        $pipeline = fake()->randomFloat(2, 100000, 2000000);

        return [
            'period' => fake()->year().'-Q'.fake()->numberBetween(1, 4),
            'user_id' => null,
            'forecast_amount' => $pipeline * 0.8,
            'commit_amount' => $pipeline * 0.6,
            'best_case' => $pipeline * 1.2,
            'pipeline_total' => $pipeline,
            'ai_prediction' => $pipeline * 0.85,
            'confidence_pct' => fake()->numberBetween(50, 95),
            'generated_at' => now(),
        ];
    }
}
