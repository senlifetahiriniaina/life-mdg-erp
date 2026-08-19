<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Modules\Accounting\Models\BankAccount;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Models\Journal;
use Modules\Accounting\Models\JournalEntry;

beforeEach(function () {
    $this->seed(\Modules\Accounting\Database\Seeders\AccountingDatabaseSeeder::class);
    $this->user = actingAsUser('accountant');
    $this->token = $this->user->createToken('test')->plainTextToken;
});

it('lists the seeded operation templates grouped by nature', function () {
    $this->withToken($this->token)
        ->getJson('/api/v1/accounting/operation-templates')
        ->assertOk()
        ->assertJsonPath('data.0.nature', 'decaissement'); // alphabetical nature ordering: decaissement before encaissement

    expect(\Modules\Accounting\Models\OperationTemplate::count())->toBeGreaterThanOrEqual(18);
});

it('previews a caisse CSV and suggests real templates without a false-positive substring match', function () {
    $csv = "date,libelle,montant\n"
        . "2026-08-01,Vente comptant client Rakoto,150000\n"
        . "2026-08-02,Paiement fournisseur SARL Import,-80000\n";

    $file = UploadedFile::fake()->createWithContent('caisse.csv', $csv);

    $response = $this->withToken($this->token)
        ->postJson('/api/v1/accounting/treasury-imports/preview', [
            'file' => $file,
            'treasury_account_code' => '530',
        ])
        ->assertOk();

    $rows = $response->json('rows');
    expect($rows)->toHaveCount(2);
    expect($rows[0]['nature'])->toBe('encaissement');
    expect($rows[0]['suggested_template_code'])->toBe('vente_comptant');
    expect($rows[1]['nature'])->toBe('decaissement');
    // Regression: "paie" must not falsely match inside "Paiement" and suggest paiement_salaire.
    expect($rows[1]['suggested_template_code'])->toBe('reglement_fournisseur');
});

it('rejects an unsupported treasury account code on preview', function () {
    $file = UploadedFile::fake()->createWithContent('caisse.csv', "date,libelle,montant\n2026-08-01,x,1000\n");

    $this->withToken($this->token)
        ->postJson('/api/v1/accounting/treasury-imports/preview', [
            'file' => $file,
            'treasury_account_code' => '999',
        ])
        ->assertStatus(422);
});

it('commits caisse rows into balanced journal entries in the CAI journal', function () {
    $response = $this->withToken($this->token)
        ->postJson('/api/v1/accounting/treasury-imports/commit', [
            'treasury_account_code' => '530',
            'rows' => [
                ['date' => '2026-08-01', 'description' => 'Vente comptant', 'amount' => 150000, 'template_code' => 'vente_comptant'],
                ['date' => '2026-08-02', 'description' => 'Loyer aout', 'amount' => -45000, 'template_code' => 'loyer'],
            ],
        ])
        ->assertCreated();

    expect($response->json('data.count'))->toBe(2);
    expect((float) $response->json('data.total_debit'))->toBe(195000.0);
    expect((float) $response->json('data.total_credit'))->toBe(195000.0);

    $caisse = Journal::where('code', 'CAI')->firstOrFail();
    $entries = JournalEntry::where('journal_id', $caisse->id)->with('lines')->get();
    expect($entries)->toHaveCount(2);

    foreach ($entries as $entry) {
        $totalDebit = $entry->lines->sum('debit');
        $totalCredit = $entry->lines->sum('credit');
        expect((float) $totalDebit)->toBe((float) $totalCredit);
        expect($entry->lines)->toHaveCount(2);
        expect($entry->status)->toBe('posted');
    }
});

it('commits bank rows and creates a pre-matched bank statement/transaction', function () {
    $bank = BankAccount::create([
        'name' => 'Compte test',
        'bank_name' => 'BOA Madagascar',
        'account_number' => 'TEST-BNK-1',
        'currency' => 'MGA',
        'current_balance' => 500000,
        'last_reconciled_balance' => 0,
        'is_active' => true,
    ]);

    $response = $this->withToken($this->token)
        ->postJson('/api/v1/accounting/treasury-imports/commit', [
            'treasury_account_code' => '512',
            'bank_account_id' => $bank->id,
            'rows' => [
                ['date' => '2026-08-05', 'description' => 'Virement client', 'amount' => 200000, 'template_code' => 'reglement_client'],
            ],
        ])
        ->assertCreated();

    $entryId = $response->json('data.entries.0');

    $statement = $bank->statements()->latest('id')->first();
    expect($statement)->not->toBeNull();
    expect($statement->matched_count)->toBe(1);

    $transaction = $statement->transactions()->first();
    expect($transaction->status)->toBe('matched');
    expect($transaction->matched_entry_id)->toBe($entryId);

    expect((float) $bank->fresh()->current_balance)->toBe(700000.0);

    $caisseJournal = Journal::where('code', 'CAI')->first();
    $bnqJournal = Journal::where('code', 'BNQ')->firstOrFail();
    $entry = JournalEntry::findOrFail($entryId);
    expect($entry->journal_id)->toBe($bnqJournal->id);
});

it('rejects a commit referencing an unknown operation template', function () {
    $this->withToken($this->token)
        ->postJson('/api/v1/accounting/treasury-imports/commit', [
            'treasury_account_code' => '530',
            'rows' => [
                ['date' => '2026-08-01', 'description' => 'x', 'amount' => 1000, 'template_code' => 'nonexistent_template'],
            ],
        ])
        ->assertStatus(422);
});

// ─── Regression tests for the JournalEntryApiController fix ────────────────

it('creates a balanced journal entry via the generic API with real lines', function () {
    $bank = ChartOfAccount::where('code', '512')->firstOrFail();
    $sales = ChartOfAccount::where('code', '707')->firstOrFail();

    $response = $this->withToken($this->token)
        ->postJson('/api/v1/accounting/journal-entries', [
            'date' => '2026-08-01',
            'description' => 'Vente diverse',
            'lines' => [
                ['account_code' => $bank->code, 'debit' => 1000, 'credit' => 0],
                ['account_code' => $sales->code, 'debit' => 0, 'credit' => 1000],
            ],
        ])
        ->assertCreated();

    $lines = $response->json('data.lines');
    expect($lines)->toHaveCount(2);
    expect($lines[0]['account']['code'])->toBe('512');
    expect($lines[1]['account']['code'])->toBe('707');
});

it('rejects an unbalanced journal entry via the generic API', function () {
    $bank = ChartOfAccount::where('code', '512')->firstOrFail();
    $sales = ChartOfAccount::where('code', '707')->firstOrFail();

    $this->withToken($this->token)
        ->postJson('/api/v1/accounting/journal-entries', [
            'date' => '2026-08-01',
            'description' => 'Écriture déséquilibrée',
            'lines' => [
                ['account_code' => $bank->code, 'debit' => 1000, 'credit' => 0],
                ['account_code' => $sales->code, 'debit' => 0, 'credit' => 500],
            ],
        ])
        ->assertStatus(422);
});

it('reverses a journal entry by swapping debit/credit on each line', function () {
    $bank = ChartOfAccount::where('code', '512')->firstOrFail();
    $sales = ChartOfAccount::where('code', '707')->firstOrFail();

    $created = $this->withToken($this->token)->postJson('/api/v1/accounting/journal-entries', [
        'date' => '2026-08-01',
        'description' => 'Vente à annuler',
        'lines' => [
            ['account_code' => $bank->code, 'debit' => 1000, 'credit' => 0],
            ['account_code' => $sales->code, 'debit' => 0, 'credit' => 1000],
        ],
    ])->json('data.id');

    $reversal = $this->withToken($this->token)
        ->postJson("/api/v1/accounting/journal-entries/{$created}/reverse")
        ->assertCreated()
        ->json('data');

    expect($reversal['lines'])->toHaveCount(2);
    $byAccount = collect($reversal['lines'])->keyBy(fn ($l) => $l['account']['code']);
    expect((float) $byAccount['512']['credit'])->toBe(1000.0);
    expect((float) $byAccount['707']['debit'])->toBe(1000.0);
});
