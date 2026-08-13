<?php

namespace Modules\Analytics\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Analytics\Models\AnomalyAlert;

class AnomalyAlertFactory extends Factory
{
    protected $model = AnomalyAlert::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
                        'detected_anomaly_id' => fake()->word(),
            'company_id' => fake()->word(),
            'user_id' => fake()->word(),
            'alert_type' => fake()->word(),
            'alert_status' => fake()->word(),
            'sent_at' => fake()->word(),
            'read_at' => fake()->word(),
            'acknowledged_at' => fake()->word(),
            'escalation_notes' => fake()->word(),
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