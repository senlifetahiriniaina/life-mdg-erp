<?php

namespace Modules\Accounting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\OpenBankingConnection;

class OpenBankingConnectionFactory extends Factory
{
    protected $model = OpenBankingConnection::class;

    /**
     * Define the model's default state.
     *
     * Chantier 19 re-verification: this was scaffold boilerplate — `bank_account_id`/
     * `created_by` set to fake()->word() (not integers, so any real FK relation like
     * BankAccount::openBankingConnections() would never resolve) and `token_expires_at`
     * set to fake()->word() (not a date, throwing InvalidFormatException the moment the
     * model's own `datetime` cast tried to parse it — confirmed empirically the first
     * time this factory was actually exercised via ->create()). `status` also used
     * generic CMS-style values ('draft'/'published'/'archived') that don't match what
     * AccOpenBankingController::sync() actually checks ('active'). Rewritten to match
     * the model's real $fillable/$casts.
     */
    public function definition(): array
    {
        return [
            'bank_account_id' => \Modules\Accounting\Models\BankAccount::factory(),
            'provider' => fake()->randomElement(['nordigen', 'plaid', 'tink', 'salt_edge']),
            'access_token' => fake()->sha256(),
            'refresh_token' => fake()->sha256(),
            'token_expires_at' => fake()->dateTimeBetween('now', '+90 days'),
            'requisition_id' => (string) fake()->uuid(),
            'status' => fake()->randomElement(['active', 'expired', 'revoked']),
            'created_by' => \App\Models\User::factory(),
        ];
    }

    /**
     * Indicate model is inactive
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'expired',
        ]);
    }

    /**
     * Indicate model is archived
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'revoked',
        ]);
    }
}