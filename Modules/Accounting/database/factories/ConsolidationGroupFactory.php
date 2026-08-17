<?php

declare(strict_types=1);

namespace Modules\Accounting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\ConsolidationGroup;

/** @extends Factory<ConsolidationGroup> */
class ConsolidationGroupFactory extends Factory
{
    protected $model = ConsolidationGroup::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company().' Group',
            'description' => fake()->sentence(),
            'currency' => 'XOF',
            'is_active' => true,
            'consolidation_method' => 'full',
            'parent_company_id' => null,
            'fiscal_year' => (int) now()->year,
            'consolidation_date' => now()->toDateString(),
            'created_by' => null,
            'status' => 'draft',
            'auto_eliminate_intercompany' => false,
        ];
    }
}
