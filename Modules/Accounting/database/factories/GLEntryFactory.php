<?php

declare(strict_types=1);

namespace Modules\Accounting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\GLAccount;
use Modules\Accounting\Models\GLEntry;

/** @extends Factory<GLEntry> */
class GLEntryFactory extends Factory
{
    protected $model = GLEntry::class;

    public function definition(): array
    {
        return [
            'gl_account_id' => GLAccount::factory(),
            'entry_date' => fake()->dateTimeBetween('-1 year', 'now'),
            'debit_amount' => fake()->randomFloat(2, 0, 50000),
            'credit_amount' => 0,
            'reference_type' => null,
            'reference_id' => null,
            'description' => fake()->optional()->sentence(),
        ];
    }
}
