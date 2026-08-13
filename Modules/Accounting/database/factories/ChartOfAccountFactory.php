<?php

declare(strict_types=1);

namespace Modules\Accounting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\ChartOfAccount;

/** @extends Factory<ChartOfAccount> */
class ChartOfAccountFactory extends Factory
{
    protected $model = ChartOfAccount::class;

    public function definition(): array
    {
        return [
            'code' => (string) fake()->unique()->numerify('####'),
            'name' => fake()->words(3, true),
            'type' => fake()->randomElement(['asset', 'liability', 'equity', 'revenue', 'expense']),
            'is_active' => true,
        ];
    }
}
