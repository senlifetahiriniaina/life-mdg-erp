<?php

namespace Modules\Accounting\Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\GlAccount;

class GlAccountFactory extends Factory
{
    protected $model = GlAccount::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'account_number' => $this->faker->unique()->numerify('####-##'),
            'account_name' => $this->faker->word(),
            'account_type' => $this->faker->randomElement(['Asset', 'Liability', 'Equity', 'Revenue', 'Expense']),
            'balance' => 0,
            'is_active' => true,
        ];
    }
}
