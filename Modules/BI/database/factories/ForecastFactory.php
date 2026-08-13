<?php

declare(strict_types=1);

namespace Modules\BI\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\BI\Models\Forecast;
use Modules\BI\Models\PredictiveModel;

/** @extends Factory<Forecast> */
class ForecastFactory extends Factory
{
    protected $model = Forecast::class;

    public function definition(): array
    {
        $value = fake()->randomFloat(2, 1000, 50000);

        return [
            'predictive_model_id' => PredictiveModel::factory(),
            'forecast_date' => now()->addDay()->toDateString(),
            'forecast_value' => $value,
            'lower_bound' => $value * 0.9,
            'upper_bound' => $value * 1.1,
            'actual_value' => null,
            'error_percent' => null,
        ];
    }
}
