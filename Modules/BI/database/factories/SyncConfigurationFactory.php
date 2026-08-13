<?php

namespace Modules\BI\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\BI\Models\SyncConfiguration;

class SyncConfigurationFactory extends Factory
{
    protected $model = SyncConfiguration::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'source_id' => fake()->word(),
            'sync_type' => fake()->word(),
            'frequency' => fake()->word(),
            'scheduled_time' => fake()->word(),
            'day_of_week' => fake()->word(),
            'day_of_month' => fake()->word(),
            'is_active' => true,
            'batch_size' => fake()->word(),
            'max_retries' => fake()->word(),
            'retry_delay_minutes' => fake()->word(),
            'filter_criteria' => fake()->word(),
            'name' => fake()->word(),
            'title' => fake()->word(),
            'description' => fake()->text(),
            'slug' => fake()->slug(),
            'status' => fake()->randomElement(['draft', 'published', 'archived']),
            'code' => fake()->bothify('??-##'),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'amount' => fake()->randomFloat(2, 0, 1000),
            'quantity' => fake()->numberBetween(1, 100),
            'price' => fake()->randomFloat(2, 0, 1000),
            'cost' => fake()->randomFloat(2, 0, 1000),
            'notes' => fake()->text(),
        ];
    }

    /**
     * Indicate model is inactive
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate model is archived
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'archived_at' => now(),
        ]);
    }
}