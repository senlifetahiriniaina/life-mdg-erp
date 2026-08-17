<?php

declare(strict_types=1);

namespace Modules\Accounting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\ConsolidationGroup;
use Modules\Accounting\Models\ConsolidationGroupEntry;

/** @extends Factory<ConsolidationGroupEntry> */
class ConsolidationGroupEntryFactory extends Factory
{
    protected $model = ConsolidationGroupEntry::class;

    public function definition(): array
    {
        return [
            'consolidation_group_id' => ConsolidationGroup::factory(),
            'entry_type' => 'intercompany_elimination',
            'related_transaction_id' => null,
            'amount' => fake()->randomFloat(4, 1000, 100000),
            'description' => fake()->sentence(),
        ];
    }
}
