<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Helpdesk\Models\CsatSurvey;

/** @extends Factory<CsatSurvey> */
class CsatSurveyFactory extends Factory
{
    protected $model = CsatSurvey::class;

    public function definition(): array
    {
        $responded = fake()->boolean(70);

        return [
            'ticket_id' => TicketFactory::new(),
            'campaign_id' => null,
            'score' => $responded ? fake()->numberBetween(1, 5) : null,
            'comment' => $responded && fake()->boolean(40) ? fake()->sentence() : null,
            'sent_at' => now()->subHours(fake()->numberBetween(1, 168)),
            'responded_at' => $responded ? now()->subHours(fake()->numberBetween(0, 72)) : null,
            'agent_id' => null,
        ];
    }

    public function responded(): static
    {
        return $this->state([
            'score' => fake()->numberBetween(1, 5),
            'responded_at' => now(),
        ]);
    }

    public function positive(): static
    {
        return $this->state([
            'score' => fake()->numberBetween(4, 5),
            'responded_at' => now(),
        ]);
    }

    public function negative(): static
    {
        return $this->state([
            'score' => fake()->numberBetween(1, 2),
            'responded_at' => now(),
        ]);
    }
}
