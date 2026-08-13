<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Helpdesk\Models\CsatCampaign;

/** @extends Factory<CsatCampaign> */
class CsatCampaignFactory extends Factory
{
    protected $model = CsatCampaign::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true).' Campaign',
            'trigger' => fake()->randomElement(['ticket_closed', 'ticket_resolved']),
            'delay_hours' => fake()->randomElement([1, 12, 24, 48]),
            'question_text' => 'How satisfied are you with our support? (1 = Very Unsatisfied, 5 = Very Satisfied)',
            'active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['active' => false]);
    }
}
