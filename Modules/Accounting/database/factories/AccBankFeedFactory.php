<?php

declare(strict_types=1);

namespace Modules\Accounting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\AccBankFeed;
use Modules\Accounting\Models\AccOpenBankingConnection;

/** @extends Factory<AccBankFeed> */
class AccBankFeedFactory extends Factory
{
    protected $model = AccBankFeed::class;

    public function definition(): array
    {
        return [
            'connection_id' => AccOpenBankingConnection::factory(),
            'external_account_id' => 'acct_'.fake()->unique()->bothify('##########'),
            'account_name' => fake()->randomElement(['Compte courant', 'Compte épargne', 'Compte professionnel']),
            'iban' => 'FR76'.fake()->numerify('####################'),
            'currency' => 'EUR',
            'balance' => fake()->randomFloat(2, -10000, 100000),
            'last_transaction_date' => fake()->optional()->dateTimeBetween('-30 days', 'now'),
        ];
    }
}
