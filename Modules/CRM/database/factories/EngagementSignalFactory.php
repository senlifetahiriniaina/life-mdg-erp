<?php

declare(strict_types=1);

namespace Modules\CRM\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\CRM\Models\EngagementSignal;
use Modules\CRM\Models\Opportunity;

class EngagementSignalFactory extends Factory
{
    protected $model = EngagementSignal::class;

    public function definition(): array
    {
        $signalType = $this->faker->randomElement([
            'email_open',
            'email_reply',
            'call_completed',
            'meeting_attended',
            'demo_requested',
            'proposal_viewed',
            'contract_sent',
            'inbound_call',
            'website_visit',
            'document_downloaded',
        ]);

        return [
            'opportunity_id' => Opportunity::factory(),
            'signal_type' => $signalType,
            'score_impact' => $this->faker->numberBetween(5, 20),
            'description' => $this->faker->sentence(),
            'source' => $this->faker->randomElement(['email', 'crm', 'web', 'manual']),
            'occurred_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'activity_id' => null,
        ];
    }
}
