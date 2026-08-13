<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Helpdesk\Models\SlaPolicy;

class SlaPolicyFactory extends Factory
{
    protected $model = SlaPolicy::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true).' SLA',
            'description' => fake()->optional()->sentence(),
            'priority' => fake()->randomElement(['low', 'normal', 'high', 'urgent', 'critical']),
            'response_time_minutes' => fake()->numberBetween(30, 240),
            'resolution_time_minutes' => fake()->numberBetween(480, 2880),
            'business_hours_only' => false,
            'escalation_enabled' => true,
            'escalation_after_minutes' => fake()->optional()->numberBetween(30, 120),
            'is_active' => true,
        ];
    }
}
