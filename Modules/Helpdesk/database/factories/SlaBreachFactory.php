<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Helpdesk\Models\SlaBreach;

class SlaBreachFactory extends Factory
{
    protected $model = SlaBreach::class;

    public function definition(): array
    {
        return [
            'ticket_id' => fake()->numberBetween(1, 100),
            'policy_id' => null,
            'breach_type' => fake()->randomElement(['response', 'resolution']),
            'breached_at' => now()->subMinutes(fake()->numberBetween(10, 120)),
            'acknowledged_at' => null,
            'breach_minutes' => fake()->numberBetween(1, 60),
            'escalated' => false,
            'escalated_at' => null,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
