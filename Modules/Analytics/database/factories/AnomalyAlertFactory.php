<?php

namespace Modules\Analytics\Database\Factories;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Analytics\Models\AnomalyAlert;
use Modules\Analytics\Models\DetectedAnomaly;

class AnomalyAlertFactory extends Factory
{
    protected $model = AnomalyAlert::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'detected_anomaly_id' => DetectedAnomaly::factory(),
            'company_id' => Company::factory(),
            'user_id' => User::factory(),
            'alert_type' => fake()->randomElement(['email', 'sms', 'push', 'in_app']),
            'alert_status' => fake()->randomElement(['sent', 'read', 'acknowledged', 'escalated']),
            'sent_at' => fake()->dateTimeBetween('-30 days', 'now'),
            'read_at' => fake()->optional()->dateTimeBetween('-30 days', 'now'),
            'acknowledged_at' => fake()->optional()->dateTimeBetween('-30 days', 'now'),
            'escalation_notes' => fake()->sentence(),
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
