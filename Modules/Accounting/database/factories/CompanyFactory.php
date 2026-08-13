<?php

declare(strict_types=1);

namespace Modules\Accounting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\Company;

/** @extends Factory<Company> */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'code' => fake()->unique()->regexify('[A-Z]{3}[0-9]{3}'),
            'parent_company_id' => null,
            'company_type' => 'subsidiary',
            'ownership_percentage' => 100.00,
            'currency' => 'USD',
            'fiscal_year_start_month' => 1,
            'is_active' => true,
            'elimination_account_id' => null,
        ];
    }

    public function parent(): static
    {
        return $this->state(fn (array $attributes) => [
            'company_type' => 'parent',
            'parent_company_id' => null,
        ]);
    }
}
