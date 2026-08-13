<?php

namespace Modules\Accounting\Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\ConsolidationHierarchy;

class ConsolidationHierarchyFactory extends Factory
{
    protected $model = ConsolidationHierarchy::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'description' => $this->faker->sentence(),
            'type' => $this->faker->randomElement(['holding', 'subsidiary', 'branch', 'division']),
            'company_id' => Company::factory(),
            'parent_company_id' => null,
            'ownership_percentage' => $this->faker->numberBetween(50, 100),
            'effective_date' => now()->subMonths(6)->toDateString(),
            'end_date' => null,
            'is_active' => true,
        ];
    }
}
