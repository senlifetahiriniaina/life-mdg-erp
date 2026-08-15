<?php

namespace Modules\Analytics\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Analytics\Models\MLModel;
use Modules\Analytics\Models\MLModelVersion;

class MLModelVersionFactory extends Factory
{
    protected $model = MLModelVersion::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'ml_model_id' => MLModel::factory(),
            'version_number' => fake()->numerify('v#.#.#'),
            'change_notes' => fake()->sentence(),
            'validation_accuracy' => fake()->randomFloat(4, 0.7, 0.99),
            'validation_precision' => fake()->randomFloat(4, 0.7, 0.99),
            'validation_recall' => fake()->randomFloat(4, 0.7, 0.99),
            'validation_f1' => fake()->randomFloat(4, 0.7, 0.99),
            'training_samples' => fake()->numberBetween(1000, 100000),
            'validation_samples' => fake()->numberBetween(100, 10000),
            'trained_at' => fake()->dateTimeBetween('-60 days', 'now'),
            'model_path' => fake()->filePath(),
            'training_config' => [
                'epochs' => fake()->numberBetween(10, 200),
                'batch_size' => fake()->randomElement([16, 32, 64]),
            ],
            'status' => 'active',
        ];
    }

    /**
     * Indicate model is inactive
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
        ]);
    }

    /**
     * Indicate model is archived
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
        ]);
    }
}
