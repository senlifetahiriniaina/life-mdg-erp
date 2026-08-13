<?php

declare(strict_types=1);

namespace Modules\CRM\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\CRM\Models\AiAgent;
use Modules\CRM\Models\AiAgentRun;

class AiAgentRunFactory extends Factory
{
    protected $model = AiAgentRun::class;

    public function definition(): array
    {
        return [
            'agent_id' => AiAgent::factory(),
            'entity_type' => fake()->randomElement(['Contact', 'Lead', 'Opportunity']),
            'entity_id' => fake()->optional(0.8)->numberBetween(1, 1000),
            'status' => fake()->randomElement(['success', 'failed', 'skipped']),
            'result' => fake()->optional(0.7)->randomElement([
                ['action' => 'add_note', 'entity_id' => 1],
                ['action' => 'create_task', 'entity_id' => 2],
            ]),
            'error_message' => null,
            'executed_at' => fake()->dateTimeBetween('-7 days', 'now'),
            'duration_ms' => fake()->optional(0.8)->numberBetween(10, 5000),
        ];
    }

    public function success(): static
    {
        return $this->state([
            'status' => 'success',
            'error_message' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state([
            'status' => 'failed',
            'error_message' => fake()->sentence(),
        ]);
    }

    public function skipped(): static
    {
        return $this->state([
            'status' => 'skipped',
            'result' => null,
        ]);
    }
}
