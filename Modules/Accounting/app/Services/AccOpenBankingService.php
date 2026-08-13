<?php

declare(strict_types=1);

namespace Modules\Accounting\Services;

use Modules\Accounting\Models\AccBankFeed;
use Modules\Accounting\Models\AccBankFeedTransaction;
use Modules\Accounting\Models\AccOpenBankingConnection;

class AccOpenBankingService
{
    /**
     * Simulate connecting to a bank via mock OAuth.
     */
    public function connect(string $bankCode, string $authCode): AccOpenBankingConnection
    {
        $bankNames = [
            'bnp' => 'BNP Paribas',
            'sg' => 'Société Générale',
            'ca' => 'Crédit Agricole',
            'lbp' => 'La Banque Postale',
            'cic' => 'CIC',
            'lcl' => 'LCL',
            'hsbc' => 'HSBC France',
        ];

        /** @var AccOpenBankingConnection $connection */
        $connection = AccOpenBankingConnection::create([
            'bank_name' => $bankNames[$bankCode] ?? strtoupper($bankCode),
            'bank_code' => $bankCode,
            'status' => 'active',
            'access_token' => 'tok_'.bin2hex(random_bytes(16)),
            'refresh_token' => 'ref_'.bin2hex(random_bytes(16)),
            'token_expires_at' => now()->addDays(90),
            'last_synced_at' => null,
            'external_account_ids' => ['acct_'.bin2hex(random_bytes(8))],
            'error_message' => null,
        ]);

        // Create a mock bank feed for the new connection
        AccBankFeed::create([
            'connection_id' => $connection->id,
            'external_account_id' => 'acct_'.bin2hex(random_bytes(8)),
            'account_name' => 'Compte courant '.($bankNames[$bankCode] ?? strtoupper($bankCode)),
            'iban' => 'FR76'.str_pad((string) random_int(0, 99999999999999999), 23, '0', STR_PAD_LEFT),
            'currency' => 'EUR',
            'balance' => number_format(random_int(1000, 50000) / 100, 2, '.', ''),
            'last_transaction_date' => null,
        ]);

        return $connection;
    }

    /**
     * Sync transactions from the bank (mock: generates random transactions).
     * Returns the number of new transactions created.
     */
    public function sync(AccOpenBankingConnection $conn): int
    {
        $conn->update(['status' => 'active', 'error_message' => null]);

        $feed = AccBankFeed::where('connection_id', $conn->id)->first();
        if ($feed === null) {
            return 0;
        }

        $mockTransactions = $this->generateMockTransactions($feed);
        $created = 0;

        foreach ($mockTransactions as $tx) {
            $exists = AccBankFeedTransaction::where('external_id', $tx['external_id'])->exists();
            if (! $exists) {
                $transaction = AccBankFeedTransaction::create($tx);
                $transaction->update([
                    'category' => $this->categorize($transaction),
                    'ai_category_suggestion' => $this->categorize($transaction),
                ]);
                $created++;
            }
        }

        $conn->update([
            'last_synced_at' => now(),
        ]);

        // Update feed balance
        $feed->update([
            'last_transaction_date' => now()->toDateString(),
        ]);

        return $created;
    }

    /**
     * Suggest a category for a transaction based on simple business rules.
     */
    public function categorize(AccBankFeedTransaction $tx): string
    {
        $description = strtolower($tx->description);

        if (str_contains($description, 'salaire') || str_contains($description, 'paie')) {
            return 'salaires';
        }
        if (str_contains($description, 'loyer') || str_contains($description, 'bail')) {
            return 'loyer';
        }
        if (str_contains($description, 'edf') || str_contains($description, 'energie') || str_contains($description, 'gaz') || str_contains($description, 'electricite')) {
            return 'energie';
        }
        if (str_contains($description, 'fournisseur') || str_contains($description, 'prlv') || str_contains($description, 'facture')) {
            return 'fournisseurs';
        }
        if (str_contains($description, 'client') || str_contains($description, 'vir') || str_contains($description, 'vente')) {
            return 'ventes';
        }
        if (str_contains($description, 'carburant') || str_contains($description, 'total') || str_contains($description, 'bp')) {
            return 'carburant';
        }
        if (str_contains($description, 'restaurant') || str_contains($description, 'repas')) {
            return 'repas';
        }
        if (str_contains($description, 'amazon') || str_contains($description, 'achat') || str_contains($description, 'cb')) {
            return 'achats';
        }
        if (str_contains($description, 'frais') || str_contains($description, 'commission')) {
            return 'frais_bancaires';
        }

        return (float) $tx->amount > 0 ? 'recettes_diverses' : 'charges_diverses';
    }

    /**
     * Match a transaction to an existing journal entry.
     */
    public function matchToJournalEntry(AccBankFeedTransaction $tx, int $journalEntryId): void
    {
        $tx->update([
            'status' => 'matched',
            'journal_entry_id' => $journalEntryId,
        ]);
    }

    /**
     * Refresh the access token for a connection (mock).
     */
    public function refreshToken(AccOpenBankingConnection $conn): void
    {
        $conn->update([
            'access_token' => 'tok_'.bin2hex(random_bytes(16)),
            'refresh_token' => 'ref_'.bin2hex(random_bytes(16)),
            'token_expires_at' => now()->addDays(90),
        ]);
    }

    /**
     * Generate mock transactions for a bank feed.
     *
     * @return array<int, array<string, mixed>>
     */
    private function generateMockTransactions(AccBankFeed $feed): array
    {
        $count = random_int(5, 15);
        $transactions = [];

        $templates = [
            ['description' => 'VIR SALAIRE EMPLOYE MARTIN', 'amount' => -2800.00, 'merchant' => 'RH Paie', 'type' => 'debit'],
            ['description' => 'PRLV FOURNISSEUR DUPONT SARL', 'amount' => -1250.00, 'merchant' => 'Dupont SARL', 'type' => 'debit'],
            ['description' => 'VIR CLIENT ACME SA FACTURE 2024', 'amount' => 4500.00, 'merchant' => 'ACME SA', 'type' => 'credit'],
            ['description' => 'PRLV EDF FACTURE ENERGIE MENSUELLE', 'amount' => -320.50, 'merchant' => 'EDF', 'type' => 'debit'],
            ['description' => 'CB AMAZON MARKETPLACE FOURNITURES', 'amount' => -89.90, 'merchant' => 'Amazon', 'type' => 'debit'],
            ['description' => 'VIR REMB FRAIS PRO DEPLACEMENT', 'amount' => -450.00, 'merchant' => null, 'type' => 'debit'],
            ['description' => 'PRLV LOYER MENSUEL BUREAU', 'amount' => -1800.00, 'merchant' => 'SCI Immobilier', 'type' => 'debit'],
            ['description' => 'CB CARBURANT TOTAL STATION', 'amount' => -95.40, 'merchant' => 'Total', 'type' => 'debit'],
            ['description' => 'VIR ACOMPTE CLIENT BETA CORP', 'amount' => 2000.00, 'merchant' => 'Beta Corp', 'type' => 'credit'],
            ['description' => 'PRLV ASSURANCE PRO ALLIANZ', 'amount' => -280.00, 'merchant' => 'Allianz', 'type' => 'debit'],
            ['description' => 'VIR PAIEMENT FACTURE GAMMA INC', 'amount' => 8750.00, 'merchant' => 'Gamma Inc', 'type' => 'credit'],
            ['description' => 'CB RESTAURANT LE MIDI DEJEUNER', 'amount' => -45.80, 'merchant' => 'Le Midi', 'type' => 'debit'],
            ['description' => 'FRAIS TENUE COMPTE MENSUEL', 'amount' => -12.00, 'merchant' => null, 'type' => 'debit'],
            ['description' => 'VIR REMBOURSEMENT TVA DGI', 'amount' => 1200.00, 'merchant' => 'DGI', 'type' => 'credit'],
            ['description' => 'PRLV TELEPHONE ORANGE PRO', 'amount' => -65.00, 'merchant' => 'Orange', 'type' => 'debit'],
        ];

        shuffle($templates);
        $selected = array_slice($templates, 0, $count);

        foreach ($selected as $index => $template) {
            $daysAgo = random_int(0, 30);
            $amount = $template['amount'] + (random_int(-500, 500) / 100);

            $transactions[] = [
                'feed_id' => $feed->id,
                'external_id' => 'tx_'.bin2hex(random_bytes(8)).'_'.$index,
                'date' => now()->subDays($daysAgo)->toDateString(),
                'amount' => number_format($amount, 2, '.', ''),
                'description' => $template['description'],
                'category' => null,
                'merchant' => $template['merchant'],
                'status' => 'new',
                'journal_entry_id' => null,
                'ai_category_suggestion' => null,
            ];
        }

        return $transactions;
    }
}
