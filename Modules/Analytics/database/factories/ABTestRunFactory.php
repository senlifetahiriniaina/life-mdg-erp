<?php

namespace Modules\Analytics\Database\Factories;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Analytics\Models\ABTestRun;
use Modules\Analytics\Models\MLModel;
use Modules\Analytics\Models\MLModelVersion;

class ABTestRunFactory extends Factory
{
    protected $model = ABTestRun::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'ml_model_id' => MLModel::factory(),
            'control_version_id' => MLModelVersion::factory(),
            'variant_version_id' => MLModelVersion::factory(),
            'test_name' => fake()->words(3, true),
            'hypothesis' => fake()->sentence(),
            'status' => fake()->randomElement(['planned', 'running', 'completed']),
            'sample_size' => fake()->numberBetween(100, 10000),
            'test_split' => fake()->randomFloat(2, 0, 100),
            'statistical_significance' => fake()->randomFloat(4, 0, 1),
            'confidence_level' => fake()->randomFloat(2, 90, 99),
            'started_at' => fake()->dateTimeBetween('-30 days', 'now'),
            'ended_at' => fake()->dateTimeBetween('now', '+30 days'),
            'results' => [
                'control_conversion' => fake()->randomFloat(4, 0, 1),
                'variant_conversion' => fake()->randomFloat(4, 0, 1),
            ],
            'winner' => fake()->randomElement(['control', 'variant']),
            'conclusion' => fake()->sentence(),
            'created_by' => User::factory(),
        ];
    }

    /**
     * Indicate model is inactive
     */
    public function inactive(): static
    {
        // ab_test_runs has no is_active column — no-op, kept for API compatibility.
        return $this->state(fn (array $attributes) => []);
    }

    /**
     * Indicate model is archived
     */
    public function archived(): static
    {
        // ab_test_runs has no archived_at column — no-op, kept for API compatibility.
        return $this->state(fn (array $attributes) => []);
    }
}
