<?php

declare(strict_types=1);

namespace Modules\Accounting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\ConsolidationGroup;
use Modules\Accounting\Models\ConsolidationMember;

/** @extends Factory<ConsolidationMember> */
class ConsolidationMemberFactory extends Factory
{
    protected $model = ConsolidationMember::class;

    public function definition(): array
    {
        return [
            'consolidation_group_id' => ConsolidationGroup::factory(),
            'subsidiary_company_id' => null,
            'ownership_percentage' => fake()->randomFloat(2, 50, 100),
            'relationship_type' => 'subsidiary',
            'acquisition_date' => now()->subYears(2)->toDateString(),
            'acquisition_price' => fake()->randomFloat(4, 100000, 2000000),
            'exchange_rate' => 1,
        ];
    }
}
