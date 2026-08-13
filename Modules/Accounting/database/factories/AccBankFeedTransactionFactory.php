<?php

declare(strict_types=1);

namespace Modules\Accounting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Accounting\Models\AccBankFeed;
use Modules\Accounting\Models\AccBankFeedTransaction;

/** @extends Factory<AccBankFeedTransaction> */
class AccBankFeedTransactionFactory extends Factory
{
    protected $model = AccBankFeedTransaction::class;

    public function definition(): array
    {
        $descriptions = [
            'VIR SALAIRE EMPLOYE',
            'PRLV FOURNISSEUR DUPONT SARL',
            'CB RESTAURANT LE MIDI',
            'VIR CLIENT MARTIN SAS',
            'PRLV EDF FACTURE ENERGIE',
            'CB AMAZON MARKETPLACE',
            'VIR REMB FRAIS PRO',
            'PRLV LOYER MENSUEL',
            'CB CARBURANT TOTAL',
            'VIR ACOMPTE CLIENT',
        ];

        return [
            'feed_id' => AccBankFeed::factory(),
            'external_id' => 'tx_'.fake()->unique()->bothify('??????????'),
            'date' => fake()->dateTimeBetween('-60 days', 'now'),
            'amount' => fake()->randomFloat(2, -5000, 5000),
            'description' => fake()->randomElement($descriptions),
            'category' => fake()->optional()->randomElement(['salaires', 'fournisseurs', 'ventes', 'charges', 'divers']),
            'merchant' => fake()->optional()->company(),
            'status' => fake()->randomElement(['new', 'matched', 'ignored']),
            'journal_entry_id' => null,
            'ai_category_suggestion' => fake()->optional()->randomElement(['salaires', 'fournisseurs', 'ventes', 'charges']),
        ];
    }

    public function statusNew(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'new',
        ]);
    }

    public function statusMatched(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'matched',
            'journal_entry_id' => fake()->numberBetween(1, 1000),
        ]);
    }

    public function statusIgnored(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'ignored',
        ]);
    }
}
