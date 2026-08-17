<?php

declare(strict_types=1);

namespace Modules\Accounting\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Models\BankAccount;
use Modules\Accounting\Models\BankStatement;
use Modules\Accounting\Models\BankTransaction;
use Modules\Accounting\Models\ReconciliationSession;

class BankReconciliationService
{
    /**
     * Create a new bank account.
     */
    public function createBankAccount(array $data): BankAccount
    {
        return BankAccount::create($data);
    }

    /**
     * Import a statement with its transactions.
     *
     * $transactions: [['date'=>'2026-01-15','description'=>'...','amount'=>1500.00,'reference'=>'REF001'], ...]
     */
    public function importStatement(BankAccount $account, array $statementData, array $transactions): BankStatement
    {
        $statement = $account->statements()->create([
            'statement_date' => $statementData['statement_date'],
            'opening_balance' => $statementData['opening_balance'],
            'closing_balance' => $statementData['closing_balance'],
            'status' => 'imported',
            'transaction_count' => count($transactions),
            'matched_count' => 0,
            'notes' => $statementData['notes'] ?? null,
        ]);

        foreach ($transactions as $txn) {
            $statement->transactions()->create([
                'transaction_date' => $txn['date'],
                'description' => $txn['description'],
                'amount' => $txn['amount'],
                'reference' => $txn['reference'] ?? null,
                'status' => 'unmatched',
            ]);
        }

        return $statement;
    }

    /**
     * Auto-match unmatched transactions across every one of an account's
     * statements (the Reconcile.vue "Auto-Match" action operates per
     * account, not per statement).
     */
    public function autoMatchAccount(BankAccount $account): int
    {
        return $account->statements()
            ->get()
            ->sum(fn (BankStatement $statement) => $this->autoMatch($statement));
    }

    /**
     * Auto-match unmatched transactions against journal entries using multi-criteria scoring.
     * Evaluates amount tolerance, date proximity, and description similarity.
     * Returns count of newly matched transactions.
     */
    public function autoMatch(BankStatement $statement): int
    {
        try {
            if (! DB::getSchemaBuilder()->hasTable('acc_journal_entries')) {
                return 0;
            }
        } catch (\Throwable) {
            return 0;
        }

        $matched = 0;
        $unmatched = $statement->transactions()
            ->where('status', 'unmatched')
            ->get();

        foreach ($unmatched as $transaction) {
            $bestMatch = $this->findBestMatch($transaction, $statement);

            if ($bestMatch !== null) {
                $this->matchTransaction($transaction, (int) $bestMatch['id']);
                $matched++;
            }
        }

        if ($matched > 0) {
            $statement->increment('matched_count', $matched);
        }

        return $matched;
    }

    /**
     * Find best matching journal entry using multi-criteria scoring.
     * Scoring criteria: amount tolerance, date proximity, description similarity.
     *
     * @return array|null ['id' => int, 'score' => float] or null if no good match found
     */
    private function findBestMatch(BankTransaction $transaction, BankStatement $statement): ?array
    {
        $candidates = DB::table('acc_journal_entries')
            ->whereBetween('amount', [
                $transaction->amount * 0.95,  // 5% tolerance
                $transaction->amount * 1.05,
            ])
            ->whereDate('entry_date', '>=', $transaction->transaction_date->subDays(5))
            ->whereDate('entry_date', '<=', $transaction->transaction_date->addDays(5))
            ->get();

        if ($candidates->isEmpty()) {
            return null;
        }

        $bestScore = 0;
        $bestMatch = null;

        foreach ($candidates as $candidate) {
            $score = $this->calculateMatchScore($transaction, $candidate);

            // Require minimum score of 0.7 (70% match) for auto-matching
            if ($score > $bestScore && $score >= 0.7) {
                $bestScore = $score;
                $bestMatch = ['id' => $candidate->id, 'score' => $score];
            }
        }

        return $bestMatch;
    }

    /**
     * Calculate composite match score (0-1) between transaction and journal entry.
     * Factors: amount tolerance (40%), date proximity (30%), description similarity (30%).
     */
    private function calculateMatchScore(BankTransaction $transaction, object $candidate): float
    {
        // Amount score: perfect match = 1.0, up to 5% variance = 0.8
        $amountDiff = abs($transaction->amount - $candidate->amount);
        $amountScore = max(0, 1.0 - ($amountDiff / $transaction->amount));

        // Date score: same day = 1.0, 5 days apart = 0.0
        $dateDiff = abs($transaction->transaction_date->diffInDays($candidate->entry_date ?? now()));
        $dateScore = max(0, 1.0 - ($dateDiff / 5.0));

        // Description similarity: Levenshtein distance
        $description = $transaction->description ?? '';
        $reference = $candidate->description ?? '';
        $maxLen = max(strlen($description), strlen($reference)) ?: 1;
        $distance = levenshtein(strtolower($description), strtolower($reference));
        $descriptionScore = max(0, 1.0 - ($distance / $maxLen));

        // Weighted composite score: 40% amount, 30% date, 30% description
        return ($amountScore * 0.4) + ($dateScore * 0.3) + ($descriptionScore * 0.3);
    }

    /**
     * Manually match a transaction to a journal entry or invoice.
     */
    public function matchTransaction(BankTransaction $transaction, int $entryId): BankTransaction
    {
        $transaction->match($entryId);

        // Increment matched_count on the parent statement
        $transaction->statement()->increment('matched_count');

        return $transaction->fresh();
    }

    /**
     * Ignore a transaction (mark as non-reconcilable noise).
     */
    public function ignoreTransaction(BankTransaction $transaction): BankTransaction
    {
        $transaction->ignore();

        return $transaction->fresh();
    }

    /**
     * Complete reconciliation: mark statement reconciled and update bank account balance.
     */
    public function reconcileStatement(BankStatement $statement): BankStatement
    {
        $statement->reconcile();

        $account = $statement->bankAccount;
        $account->updateBalance((float) $statement->closing_balance);
        $account->markReconciled((float) $statement->closing_balance);

        return $statement->fresh();
    }

    /**
     * Get unmatched transactions for a statement.
     */
    public function getUnmatched(BankStatement $statement): Collection
    {
        return $statement->transactions()
            ->where('status', 'unmatched')
            ->get();
    }

    /**
     * Get a high-level reconciliation summary.
     *
     * @return array{total_accounts: int, last_reconciled: string|null, unmatched_transactions: int, total_unmatched_amount: float}
     */
    public function getReconciliationSummary(): array
    {
        $totalAccounts = BankAccount::count();

        $lastReconciled = BankAccount::whereNotNull('last_reconciled_at')
            ->orderByDesc('last_reconciled_at')
            ->value('last_reconciled_at');

        $unmatchedCount = BankTransaction::where('status', 'unmatched')->count();

        $totalUnmatchedAmount = (float) BankTransaction::where('status', 'unmatched')
            ->sum('amount');

        return [
            'total_accounts' => $totalAccounts,
            'last_reconciled' => $lastReconciled ? (string) $lastReconciled : null,
            'unmatched_transactions' => $unmatchedCount,
            'total_unmatched_amount' => $totalUnmatchedAmount,
        ];
    }

    /**
     * Get all statements for a bank account.
     */
    public function getAccountStatements(BankAccount $account): Collection
    {
        return $account->statements()->latest('statement_date')->get();
    }

    /**
     * Complete a reconciliation session: mark it completed and update the
     * bank account's reconciled balance from the session's closing balance.
     */
    public function completeReconciliation(ReconciliationSession $session): ReconciliationSession
    {
        $session->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        if ($session->closing_balance !== null) {
            $session->bankAccount->markReconciled((float) $session->closing_balance);
        }

        return $session->fresh();
    }
}
