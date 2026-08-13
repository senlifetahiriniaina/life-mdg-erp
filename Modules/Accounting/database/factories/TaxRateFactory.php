<?php

declare(strict_types=1);

namespace Modules\Accounting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\TaxRate;

/** @extends Factory<TaxRate> */
class TaxRateFactory extends Factory
{
    protected $model = TaxRate::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true).' Tax',
            'code' => strtoupper(fake()->unique()->lexify('TAX????')),
            'rate' => fake()->randomFloat(4, 1, 30),
            'type' => fake()->randomElement(['vat', 'sales_tax', 'withholding', 'custom']),
            'country' => fake()->optional()->countryCode(),
            'is_active' => true,
            'is_compound' => false,
            'applies_to' => fake()->randomElement(['all', 'goods', 'services']),
        ];
    }

    public function active(): static
    {
        return $this->state(['is_active' => true]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function compound(): static
    {
        return $this->state(['is_compound' => true]);
    }

    public function vat(): static
    {
        return $this->state(['type' => 'vat']);
    }
}
