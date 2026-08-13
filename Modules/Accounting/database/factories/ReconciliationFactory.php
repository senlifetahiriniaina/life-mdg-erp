<?php

namespace Modules\Accounting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\Reconciliation;
use Modules\Accounting\Models\BankAccount;

class ReconciliationFactory extends Factory
{
    protected $model = Reconciliation::class;

    public function definition(): array
    {
        $systemBalance = $this->faker->randomFloat(2, 0, 100000);
        $bankStatementBalance = $systemBalance + $this->faker->randomFloat(2, -100, 100);
        $differenceAmount = abs($systemBalance - $bankStatementBalance);

        return [
            'bank_account_id' => BankAccount::factory(),
            'reconciliation_date' => $this->faker->date(),
            'system_balance' => $systemBalance,
            'bank_statement_balance' => $bankStatementBalance,
            'difference_amount' => $differenceAmount,
            'status' => $differenceAmount < 0.01 ? 'reconciled' : 'in_progress',
        ];
    }
}
