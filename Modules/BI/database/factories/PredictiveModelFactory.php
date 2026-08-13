<?php

declare(strict_types=1);

namespace Modules\BI\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\BI\Models\PredictiveModel;

/** @extends Factory<PredictiveModel> */
class PredictiveModelFactory extends Factory
{
    protected $model = PredictiveModel::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true).' Model',
            'entity_type' => 'revenue',
            'model_type' => 'linear_regression',
            'training_data' => [],
            'coefficients' => null,
            'accuracy_score' => null,
            'last_trained_at' => null,
            'forecast_horizon_days' => 30,
            'is_active' => true,
        ];
    }
}
