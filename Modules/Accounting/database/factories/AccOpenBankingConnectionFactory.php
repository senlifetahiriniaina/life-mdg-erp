<?php

declare(strict_types=1);

namespace Modules\Accounting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\AccOpenBankingConnection;

/** @extends Factory<AccOpenBankingConnection> */
class AccOpenBankingConnectionFactory extends Factory
{
    protected $model = AccOpenBankingConnection::class;

    public function definition(): array
    {
        $banks = [
            ['name' => 'BNP Paribas', 'code' => 'bnp'],
            ['name' => 'Société Générale', 'code' => 'sg'],
            ['name' => 'Crédit Agricole', 'code' => 'ca'],
            ['name' => 'La Banque Postale', 'code' => 'lbp'],
            ['name' => 'CIC', 'code' => 'cic'],
        ];
        $bank = fake()->randomElement($banks);

        return [
            'bank_name' => $bank['name'],
            'bank_code' => $bank['code'],
            'status' => fake()->randomElement(['active', 'inactive', 'error']),
            'access_token' => fake()->optional()->sha256(),
            'refresh_token' => fake()->optional()->sha256(),
            'token_expires_at' => fake()->optional()->dateTimeBetween('now', '+90 days'),
            'last_synced_at' => fake()->optional()->dateTimeBetween('-7 days', 'now'),
            'external_account_ids' => [fake()->uuid(), fake()->uuid()],
            'error_message' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'access_token' => 'tok_'.fake()->sha256(),
            'token_expires_at' => now()->addDays(90),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
            'access_token' => null,
        ]);
    }
}
