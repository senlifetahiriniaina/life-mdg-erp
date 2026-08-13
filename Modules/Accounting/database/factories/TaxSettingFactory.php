<?php

namespace Modules\Accounting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\TaxSetting;

class TaxSettingFactory extends Factory
{
    protected $model = TaxSetting::class;

    public function definition(): array
    {
        return [
            'tax_name' => $this->faker->unique()->words(2, true),
            'tax_rate' => $this->faker->randomElement([5, 10, 15, 20]),
            'tax_type' => $this->faker->randomElement(['sales_tax', 'income_tax', 'vat', 'other']),
            'status' => 'active',
            'description' => $this->faker->sentence(),
        ];
    }
}
