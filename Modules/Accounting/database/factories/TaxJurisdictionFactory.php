<?php

namespace Modules\Accounting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\TaxJurisdiction;

class TaxJurisdictionFactory extends Factory
{
    protected $model = TaxJurisdiction::class;

    public function definition(): array
    {
        return [
            'jurisdiction_code' => $this->faker->unique()->countryCode() . '-' . $this->faker->numerify('##'),
            'jurisdiction_name' => $this->faker->state() . ' Sales Tax',
            'country_code' => $this->faker->unique()->countryCode(),
            'region_code' => $this->faker->stateAbbr(),
            'tax_type' => $this->faker->randomElement(['VAT', 'GST', 'Sales Tax', 'Income Tax']),
            'tax_rate' => $this->faker->numberBetween(50, 2500) / 100,
            'effective_from' => now()->subYears(5)->toDateString(),
            'effective_to' => null,
            'tax_calculation_method' => 'exclusive',
            'is_active' => true,
        ];
    }
}
