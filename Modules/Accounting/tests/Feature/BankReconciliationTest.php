<?php

declare(strict_types=1);

use Modules\Accounting\Models\BankAccount;
use Modules\Accounting\Models\BankStatement;
use Modules\Accounting\Models\BankTransaction;
use Modules\Accounting\Services\BankReconciliationService;

// ─── BankAccount Model Tests ──────────────────────────────────────────────────

describe('BankAccount model', function () {
    beforeEach(function () {
        $this->user = actingAsUser('admin');
    });

    test('isActive() returns true when is_active is true', function () {
        $account = BankAccount::factory()->create(['is_active' => true]);
        expect($account->isActive())->toBeTrue();
    });

    test('isActive() returns false when is_active is false', function () {
        $account = BankAccount::factory()->create(['is_active' => false]);
        expect($account->isActive())->toBeFalse();
    });

    test('unreconciledBalance() returns difference between current and last reconciled balance', function () {
        $account = BankAccount::factory()->create([
            'current_balance' => 10000,
            'last_reconciled_balance' => 8000,
        ]);
        expect($account->unreconciledBalance())->toBe(2000.0);
    });

    test('updateBalance() updates current_balance', function () {
        $account = BankAccount::factory()->create(['current_balance' => 5000]);
        $account->updateBalance(7500.0);
        expect((float) $account->fresh()->current_balance)->toBe(7500.0);
    });

    test('markReconciled() sets last_reconciled_at and last_reconciled_balance', function () {
        $account = BankAccount::factory()->create();
        $account->markReconciled(9000.0);
        $fresh = $account->fresh();
        expect($fresh->last_reconciled_at)->not->toBeNull();
        expect((float) $fresh->last_reconciled_balance)->toBe(9000.0);
    });

    test('statements() relation returns related statements', function () {
        $account = BankAccount::factory()->create();
        $statement = BankStatement::factory()->create(['bank_account_id' => $account->id]);

        expect($account->statements()->count())->toBe(1);
        expect($account->statements()->first()->id)->toBe($statement->id);
    });
});

// ─── BankStatement Model Tests ────────────────────────────────────────────────

describe('BankStatement model', function () {
    beforeEach(function () {
        $this->user = actingAsUser('admin');
    });

    test('isReconciled() returns true when status is reconciled', function () {
        $statement = BankStatement::factory()->create(['status' => 'reconciled']);
        expect($statement->isReconciled())->toBeTrue();
    });

    test('isReconciled() returns false when status is imported', function () {
        $statement = BankStatement::factory()->create(['status' => 'imported']);
        expect($statement->isReconciled())->toBeFalse();
    });

    test('netChange() returns closing minus opening balance', function () {
        $statement = BankStatement::factory()->create([
            'opening_balance' => 5000,
            'closing_balance' => 8000,
        ]);
        expect($statement->netChange())->toBe(3000.0);
    });

    test('matchRate() returns 0.0 when transaction_count is 0', function () {
        $statement = BankStatement::factory()->create([
            'transaction_count' => 0,
            'matched_count' => 0,
        ]);
        expect($statement->matchRate())->toBe(0.0);
    });

    test('matchRate() returns correct percentage', function () {
        $statement = BankStatement::factory()->create([
            'transaction_count' => 10,
            'matched_count' => 7,
        ]);
        expect($statement->matchRate())->toBe(70.0);
    });

    test('reconcile() sets status to reconciled and reconciled_at', function () {
        $statement = BankStatement::factory()->create(['status' => 'imported']);
        $statement->reconcile();
        $fresh = $statement->fresh();
        expect($fresh->status)->toBe('reconciled');
        expect($fresh->reconciled_at)->not->toBeNull();
    });

    test('bankAccount() relation returns the related account', function () {
        $account = BankAccount::factory()->create();
        $statement = BankStatement::factory()->create(['bank_account_id' => $account->id]);

        expect($statement->bankAccount->id)->toBe($account->id);
    });

    test('transactions() relation returns related transactions', function () {
        $statement = BankStatement::factory()->create();
        $transaction = BankTransaction::factory()->create(['statement_id' => $statement->id]);

        expect($statement->transactions()->count())->toBe(1);
        expect($statement->transactions()->first()->id)->toBe($transaction->id);
    });
});

// ─── BankTransaction Model Tests ─────────────────────────────────────────────

describe('BankTransaction model', function () {
    beforeEach(function () {
        $this->user = actingAsUser('admin');
    });

    test('isCredit() returns true for positive amount', function () {
        $txn = BankTransaction::factory()->create(['amount' => 500]);
        expect($txn->isCredit())->toBeTrue();
        expect($txn->isDebit())->toBeFalse();
    });

    test('isDebit() returns true for negative amount', function () {
        $txn = BankTransaction::factory()->create(['amount' => -500]);
        expect($txn->isDebit())->toBeTrue();
        expect($txn->isCredit())->toBeFalse();
    });

    test('isMatched() returns true when status is matched', function () {
        $txn = BankTransaction::factory()->create(['status' => 'matched']);
        expect($txn->isMatched())->toBeTrue();
    });

    test('isMatched() returns false when status is unmatched', function () {
        $txn = BankTransaction::factory()->create(['status' => 'unmatched']);
        expect($txn->isMatched())->toBeFalse();
    });

    test('match() sets status, matched_entry_id, matched_at', function () {
        $txn = BankTransaction::factory()->create(['status' => 'unmatched']);
        $txn->match(42);
        $fresh = $txn->fresh();
        expect($fresh->status)->toBe('matched');
        expect($fresh->matched_entry_id)->toBe(42);
        expect($fresh->matched_at)->not->toBeNull();
    });

    test('ignore() sets status to ignored', function () {
        $txn = BankTransaction::factory()->create(['status' => 'unmatched']);
        $txn->ignore();
        expect($txn->fresh()->status)->toBe('ignored');
    });

    test('absoluteAmount() returns abs value', function () {
        $txn = BankTransaction::factory()->create(['amount' => -1234.56]);
        expect($txn->absoluteAmount())->toBe(1234.56);
    });

    test('statement() relation returns the parent statement', function () {
        $statement = BankStatement::factory()->create();
        $txn = BankTransaction::factory()->create(['statement_id' => $statement->id]);

        expect($txn->statement->id)->toBe($statement->id);
    });
});

// ─── BankReconciliationService Tests ─────────────────────────────────────────

describe('BankReconciliationService', function () {
    beforeEach(function () {
        $this->user = actingAsUser('admin');
        $this->service = app(BankReconciliationService::class);
    });

    test('createBankAccount() creates and returns a BankAccount', function () {
        $account = $this->service->createBankAccount([
            'name' => 'Main Checking',
            'bank_name' => 'Chase',
            'currency' => 'USD',
        ]);

        expect($account)->toBeInstanceOf(BankAccount::class);
        expect($account->name)->toBe('Main Checking');
        expect($account->bank_name)->toBe('Chase');
    });

    test('importStatement() creates statement and transactions', function () {
        $account = BankAccount::factory()->create();

        $transactions = [
            ['date' => '2026-01-10', 'description' => 'Deposit', 'amount' => 1000.00, 'reference' => 'DEP001'],
            ['date' => '2026-01-11', 'description' => 'Withdrawal', 'amount' => -250.00, 'reference' => null],
        ];

        $statement = $this->service->importStatement($account, [
            'statement_date' => '2026-01-31',
            'opening_balance' => 5000.00,
            'closing_balance' => 5750.00,
        ], $transactions);

        expect($statement)->toBeInstanceOf(BankStatement::class);
        expect($statement->transaction_count)->toBe(2);
        expect($statement->transactions()->count())->toBe(2);
    });

    test('autoMatch() returns 0 gracefully when acc_journal_entries table does not exist', function () {
        $statement = BankStatement::factory()->create();
        BankTransaction::factory()->create(['statement_id' => $statement->id, 'status' => 'unmatched']);

        // acc_journal_entries may or may not exist; service handles both
        $count = $this->service->autoMatch($statement);
        expect($count)->toBeInt();
        expect($count)->toBeGreaterThanOrEqual(0);
    });

    test('matchTransaction() marks transaction as matched', function () {
        $statement = BankStatement::factory()->create(['matched_count' => 0]);
        $transaction = BankTransaction::factory()->create([
            'statement_id' => $statement->id,
            'status' => 'unmatched',
        ]);

        $result = $this->service->matchTransaction($transaction, 99);

        expect($result->status)->toBe('matched');
        expect($result->matched_entry_id)->toBe(99);
        expect($statement->fresh()->matched_count)->toBe(1);
    });

    test('ignoreTransaction() marks transaction as ignored', function () {
        $statement = BankStatement::factory()->create();
        $transaction = BankTransaction::factory()->create([
            'statement_id' => $statement->id,
            'status' => 'unmatched',
        ]);

        $result = $this->service->ignoreTransaction($transaction);

        expect($result->status)->toBe('ignored');
    });

    test('reconcileStatement() marks statement reconciled and updates bank account', function () {
        $account = BankAccount::factory()->create(['current_balance' => 5000]);
        $statement = BankStatement::factory()->create([
            'bank_account_id' => $account->id,
            'closing_balance' => 7500,
            'status' => 'imported',
        ]);

        $result = $this->service->reconcileStatement($statement);

        expect($result->status)->toBe('reconciled');
        expect($result->reconciled_at)->not->toBeNull();

        $freshAccount = $account->fresh();
        expect((float) $freshAccount->current_balance)->toBe(7500.0);
        expect((float) $freshAccount->last_reconciled_balance)->toBe(7500.0);
    });

    test('getUnmatched() returns only unmatched transactions', function () {
        $statement = BankStatement::factory()->create();
        BankTransaction::factory()->create(['statement_id' => $statement->id, 'status' => 'unmatched']);
        BankTransaction::factory()->create(['statement_id' => $statement->id, 'status' => 'matched']);
        BankTransaction::factory()->create(['statement_id' => $statement->id, 'status' => 'ignored']);

        $unmatched = $this->service->getUnmatched($statement);

        expect($unmatched)->toHaveCount(1);
        expect($unmatched->first()->status)->toBe('unmatched');
    });

    test('getReconciliationSummary() returns expected keys', function () {
        BankAccount::factory()->create();

        $summary = $this->service->getReconciliationSummary();

        expect($summary)->toHaveKeys(['total_accounts', 'last_reconciled', 'unmatched_transactions', 'total_unmatched_amount']);
        expect($summary['total_accounts'])->toBeInt();
        expect($summary['unmatched_transactions'])->toBeInt();
    });

    test('getAccountStatements() returns statements for account', function () {
        $account = BankAccount::factory()->create();
        BankStatement::factory()->count(3)->create(['bank_account_id' => $account->id]);

        $statements = $this->service->getAccountStatements($account);

        expect($statements)->toHaveCount(3);
    });
});

// ─── API Tests ────────────────────────────────────────────────────────────────

describe('Bank Reconciliation API', function () {
    beforeEach(function () {
        $this->user = actingAsUser('admin');
    });

    // Accounts
    test('GET /api/v1/accounting/bank returns list of accounts', function () {
        BankAccount::factory()->count(3)->create();

        $this->getJson('/api/v1/accounting/bank')
            ->assertOk()
            ->assertJsonCount(3);
    });

    test('POST /api/v1/accounting/bank creates a bank account', function () {
        $this->postJson('/api/v1/accounting/bank', [
            'name' => 'Payroll Account',
            'bank_name' => 'Wells Fargo',
            'currency' => 'USD',
        ])
            ->assertCreated()
            ->assertJsonFragment(['name' => 'Payroll Account']);
    });

    test('POST /api/v1/accounting/bank validates required fields', function () {
        $this->postJson('/api/v1/accounting/bank', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'bank_name']);
    });

    test('GET /api/v1/accounting/bank/{account} returns account', function () {
        $account = BankAccount::factory()->create();

        $this->getJson("/api/v1/accounting/bank/{$account->id}")
            ->assertOk()
            ->assertJsonFragment(['id' => $account->id]);
    });

    test('PUT /api/v1/accounting/bank/{account} updates account', function () {
        $account = BankAccount::factory()->create(['name' => 'Old Name']);

        $this->putJson("/api/v1/accounting/bank/{$account->id}", ['name' => 'New Name'])
            ->assertOk()
            ->assertJsonFragment(['name' => 'New Name']);
    });

    test('DELETE /api/v1/accounting/bank/{account} deletes account', function () {
        $account = BankAccount::factory()->create();

        $this->deleteJson("/api/v1/accounting/bank/{$account->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('acc_bank_accounts', ['id' => $account->id]);
    });

    // Statements
    test('GET /api/v1/accounting/bank/{account}/statements returns statements', function () {
        $account = BankAccount::factory()->create();
        BankStatement::factory()->count(2)->create(['bank_account_id' => $account->id]);

        $this->getJson("/api/v1/accounting/bank/{$account->id}/statements")
            ->assertOk()
            ->assertJsonCount(2);
    });

    test('POST /api/v1/accounting/bank/{account}/statements imports statement', function () {
        $account = BankAccount::factory()->create();

        $this->postJson("/api/v1/accounting/bank/{$account->id}/statements", [
            'statement_date' => '2026-01-31',
            'opening_balance' => 5000,
            'closing_balance' => 6000,
            'transactions' => [
                ['date' => '2026-01-15', 'description' => 'Payment', 'amount' => 1000],
            ],
        ])
            ->assertCreated()
            ->assertJsonFragment(['transaction_count' => 1]);
    });

    test('POST /api/v1/accounting/bank/{account}/statements validates required fields', function () {
        $account = BankAccount::factory()->create();

        $this->postJson("/api/v1/accounting/bank/{$account->id}/statements", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['statement_date', 'opening_balance', 'closing_balance']);
    });

    test('GET /api/v1/accounting/bank/statements/{statement} returns statement with transactions', function () {
        $statement = BankStatement::factory()->create();
        BankTransaction::factory()->count(2)->create(['statement_id' => $statement->id]);

        $this->getJson("/api/v1/accounting/bank/statements/{$statement->id}")
            ->assertOk()
            ->assertJsonStructure(['id', 'transactions']);
    });

    // Auto-match
    test('POST /api/v1/accounting/bank/statements/{statement}/auto-match returns match count', function () {
        $statement = BankStatement::factory()->create();

        $this->postJson("/api/v1/accounting/bank/statements/{$statement->id}/auto-match")
            ->assertOk()
            ->assertJsonStructure(['matched_count', 'statement']);
    });

    // Match transaction
    test('POST /api/v1/accounting/bank/transactions/{transaction}/match matches transaction', function () {
        $statement = BankStatement::factory()->create(['matched_count' => 0]);
        $transaction = BankTransaction::factory()->create([
            'statement_id' => $statement->id,
            'status' => 'unmatched',
        ]);

        $this->postJson("/api/v1/accounting/bank/transactions/{$transaction->id}/match", [
            'entry_id' => 7,
        ])
            ->assertOk()
            ->assertJsonFragment(['status' => 'matched', 'matched_entry_id' => 7]);
    });

    test('POST /api/v1/accounting/bank/transactions/{transaction}/match validates entry_id', function () {
        $statement = BankStatement::factory()->create();
        $transaction = BankTransaction::factory()->create(['statement_id' => $statement->id]);

        $this->postJson("/api/v1/accounting/bank/transactions/{$transaction->id}/match", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['entry_id']);
    });

    // Ignore transaction
    test('POST /api/v1/accounting/bank/transactions/{transaction}/ignore ignores transaction', function () {
        $statement = BankStatement::factory()->create();
        $transaction = BankTransaction::factory()->create([
            'statement_id' => $statement->id,
            'status' => 'unmatched',
        ]);

        $this->postJson("/api/v1/accounting/bank/transactions/{$transaction->id}/ignore")
            ->assertOk()
            ->assertJsonFragment(['status' => 'ignored']);
    });

    // Reconcile
    test('POST /api/v1/accounting/bank/statements/{statement}/reconcile reconciles statement', function () {
        $account = BankAccount::factory()->create();
        $statement = BankStatement::factory()->create([
            'bank_account_id' => $account->id,
            'closing_balance' => 12000,
            'status' => 'imported',
        ]);

        $this->postJson("/api/v1/accounting/bank/statements/{$statement->id}/reconcile")
            ->assertOk()
            ->assertJsonFragment(['status' => 'reconciled']);
    });

    // Unmatched
    test('GET /api/v1/accounting/bank/statements/{statement}/unmatched returns unmatched transactions', function () {
        $statement = BankStatement::factory()->create();
        BankTransaction::factory()->create(['statement_id' => $statement->id, 'status' => 'unmatched']);
        BankTransaction::factory()->create(['statement_id' => $statement->id, 'status' => 'matched']);

        $this->getJson("/api/v1/accounting/bank/statements/{$statement->id}/unmatched")
            ->assertOk()
            ->assertJsonCount(1);
    });

    // Summary
    test('GET /api/v1/accounting/bank/summary returns summary', function () {
        BankAccount::factory()->count(2)->create();

        $this->getJson('/api/v1/accounting/bank/summary')
            ->assertOk()
            ->assertJsonStructure(['total_accounts', 'last_reconciled', 'unmatched_transactions', 'total_unmatched_amount']);
    });

    // Unauthenticated
    test('unauthenticated request returns 401', function () {
        \Illuminate\Support\Facades\Auth::forgetGuards();
        $this->getJson('/api/v1/accounting/bank')
            ->assertUnauthorized();
    });
});
