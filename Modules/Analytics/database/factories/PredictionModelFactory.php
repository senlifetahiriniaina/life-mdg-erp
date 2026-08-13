<?php

namespace Modules\Analytics\Database\Factories;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Analytics\Models\PredictionModel;

class PredictionModelFactory extends Factory
{
    protected $model = PredictionModel::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'model_name' => $this->faker->words(3, true),
            'model_type' => $this->faker->randomElement(['churn', 'revenue', 'demand', 'attrition']),
            'status' => 'draft',
            'description' => $this->faker->paragraph(),
            'configuration' => [
                'threshold' => $this->faker->randomFloat(2, 0, 1),
                'features' => $this->faker->words(5),
            ],
            'created_by' => User::factory(),
        ];
    }

    public function training(): static
    {
        return $this->state(['status' => 'training']);
    }

    public function active(): static
    {
        return $this->state([
            'status' => 'active',
            'training_accuracy' => $this->faker->randomFloat(4, 0.7, 0.99),
            'validation_accuracy' => $this->faker->randomFloat(4, 0.7, 0.99),
            'trained_at' => now(),
        ]);
    }
}
