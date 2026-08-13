<?php

declare(strict_types=1);

namespace Modules\HR\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\HR\Models\SalaryBand;

class SalaryBandFactory extends Factory
{
    protected $model = SalaryBand::class;

    public function definition(): array
    {
        $min = fake()->numberBetween(25000, 80000);
        $mid = (int) ($min * 1.2);
        $max = (int) ($min * 1.5);

        return [
            'title' => fake()->jobTitle(),
            'level' => 'L'.fake()->numberBetween(1, 8),
            'min_salary' => $min.'.00',
            'mid_salary' => $mid.'.00',
            'max_salary' => $max.'.00',
            'currency' => fake()->randomElement(['EUR', 'USD', 'GBP']),
        ];
    }
}
