<?php

declare(strict_types=1);

namespace Modules\CRM\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\CRM\Models\AiAgent;

class AiAgentFactory extends Factory
{
    protected $model = AiAgent::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'description' => fake()->optional(0.7)->sentence(),
            'trigger_type' => fake()->randomElement(['schedule', 'event', 'manual']),
            'trigger_config' => fake()->optional(0.6)->randomElement([
                ['cron' => '0 9 * * *'],
                ['event' => 'contact.created'],
                null,
            ]),
            'action_type' => fake()->randomElement([
                'send_email', 'create_task', 'update_field', 'add_note', 'score_lead', 'assign_owner',
            ]),
            'action_config' => fake()->optional(0.5)->randomElement([
                ['subject' => 'Follow up', 'body' => 'Hello!'],
                ['field' => 'status', 'value' => 'active'],
                null,
            ]),
            'conditions' => fake()->optional(0.5)->randomElement([
                [['field' => 'score', 'operator' => 'gt', 'value' => 50]],
                null,
            ]),
            'is_active' => true,
            'last_run_at' => null,
            'run_count' => 0,
            'created_by' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(['is_active' => true]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function scheduled(): static
    {
        return $this->state([
            'trigger_type' => 'schedule',
            'trigger_config' => ['cron' => '0 9 * * *'],
        ]);
    }

    public function event(): static
    {
        return $this->state([
            'trigger_type' => 'event',
            'trigger_config' => ['event' => 'contact.created'],
        ]);
    }

    public function manual(): static
    {
        return $this->state(['trigger_type' => 'manual']);
    }
}
