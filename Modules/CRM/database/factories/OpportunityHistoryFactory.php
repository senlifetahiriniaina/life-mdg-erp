<?php

namespace Modules\CRM\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\CRM\Models\Opportunity;
use Modules\CRM\Models\OpportunityHistory;

/**
 * Chantier 32.15: this factory was scaffold boilerplate (fake()->word() on every column,
 * including changed_at — a real `datetime` cast) — confirmed real via the audit's own new
 * OpportunityHistoryController test, which fataled with a Carbon parse error on the first
 * real use of this factory (`changed_at` resolving to a random word like "ab"). Rewritten to
 * match the model's real $fillable/$casts.
 */
class OpportunityHistoryFactory extends Factory
{
    protected $model = OpportunityHistory::class;

    public function definition(): array
    {
        return [
            'opportunity_id' => Opportunity::factory(),
            'field' => fake()->randomElement(['stage_id', 'status', 'amount', 'expected_close_date', 'owner_id']),
            'old_value' => fake()->word(),
            'new_value' => fake()->word(),
            'changed_by' => null,
            'changed_at' => fake()->dateTimeBetween('-6 months', 'now'),
        ];
    }
}
