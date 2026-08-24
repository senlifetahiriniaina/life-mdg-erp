<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Modules\Accounting\Models\BankAccount;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Models\Journal;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\OperationTemplate;

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

    expect(OperationTemplate::count())->toBeGreaterThanOrEqual(50);
});

// ─── Chantier 36 — dynamic treasury-account resolution ─────────────────────

it('lists every active class-5 treasury account with its journal, excluding the 59 provisions family', function () {
    $response = $this->withToken($this->token)
        ->getJson('/api/v1/accounting/treasury-accounts')
        ->assertOk();

    $accounts = collect($response->json('data'))->keyBy('code');

    expect($accounts->has('5711'))->toBeTrue(); // Caisse principale
    expect($accounts->has('5721'))->toBeTrue(); // Caisse secondaire — proves this is NOT hardcoded to one caisse
    expect($accounts->has('5211'))->toBeTrue(); // Banque
    expect($accounts->has('5521'))->toBeTrue(); // Mvola
    expect($accounts['5711']['journal'])->toBe('CAI');
    expect($accounts['5211']['journal'])->toBe('BNQ');
    expect($accounts->has('59'))->toBeFalse(); // Dépréciations et provisions — not a real treasury destination
});

it('resolves the chart-of-accounts hierarchy via real parent_id links', function () {
    $bank = ChartOfAccount::where('code', '5211')->firstOrFail();
    $parent = ChartOfAccount::find($bank->parent_id);

    expect($parent)->not->toBeNull();
    expect($parent->code)->toBe('52');

    $newLiability = ChartOfAccount::where('code', '422')->firstOrFail();
    expect($newLiability->type)->toBe('liability');

    // No account should reference a parent_id that doesn't exist.
    $orphans = ChartOfAccount::query()->whereNotNull('parent_id')->get()
        ->filter(fn ($a) => ! ChartOfAccount::where('id', $a->parent_id)->exists());
    expect($orphans)->toHaveCount(0);
});

it('previews a caisse CSV and suggests real templates without a false-positive substring match', function () {
    $csv = "date,libelle,montant\n"
        . "2026-08-01,Vente comptant client Rakoto,150000\n"
        . "2026-08-02,Paiement fournisseur SARL Import,-80000\n";

    $file = UploadedFile::fake()->createWithContent('caisse.csv', $csv);

    $response = $this->withToken($this->token)
        ->postJson('/api/v1/accounting/treasury-imports/preview', [
            'file' => $file,
            'treasury_account_code' => '5711',
        ])
        ->assertOk();

    $rows = $response->json('rows');
    expect($rows)->toHaveCount(2);
    expect($rows[0]['nature'])->toBe('encaissement');
    expect($rows[0]['suggested_template_code'])->toBe('vente_au_comptant_boutique');
    expect($rows[1]['nature'])->toBe('decaissement');
    expect($rows[1]['suggested_template_code'])->toBe('paiement_fournisseur');
});

it('does not let a keyword falsely match inside an unrelated longer word (word-boundary regression)', function () {
    // The real seeded template set no longer carries a bare "paie" keyword
    // (the concrete false-positive this bug was originally caught on), but
    // the underlying matchesKeyword() word-boundary behavior is still real
    // code that must keep working — proven here against an ad-hoc template
    // rather than relying on the seeded catalogue happening to still
    // contain that exact collision. "paiements" (plural) contains "paie"
    // as a plain substring but is NOT the same word — under the old naive
    // str_contains() this would have falsely matched; with the real
    // word-boundary regex it must not, so this row falls through to the
    // generic decaissement fallback rather than the ad-hoc test template.
    OperationTemplate::create([
        'code' => 'test_paie_keyword',
        'label' => 'Paie (test)',
        'nature' => 'decaissement',
        'counterpart_account_code' => '661',
        'keywords' => ['paie'],
        'is_active' => true,
    ]);

    $csv = "date,libelle,montant\n2026-08-02,Traitement des paiements groupes,-80000\n";
    $file = UploadedFile::fake()->createWithContent('caisse.csv', $csv);

    $response = $this->withToken($this->token)
        ->postJson('/api/v1/accounting/treasury-imports/preview', [
            'file' => $file,
            'treasury_account_code' => '5711',
        ])
        ->assertOk();

    $rows = $response->json('rows');
    expect($rows[0]['suggested_template_code'])->toBe('frais_divers');
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

it('rejects a real but non-treasury account code (class 59 provisions) on preview', function () {
    $file = UploadedFile::fake()->createWithContent('caisse.csv', "date,libelle,montant\n2026-08-01,x,1000\n");

    $this->withToken($this->token)
        ->postJson('/api/v1/accounting/treasury-imports/preview', [
            'file' => $file,
            'treasury_account_code' => '59',
        ])
        ->assertStatus(422);
});

it('commits caisse rows into balanced journal entries in the CAI journal', function () {
    $response = $this->withToken($this->token)
        ->postJson('/api/v1/accounting/treasury-imports/commit', [
            'treasury_account_code' => '5711',
            'rows' => [
                ['date' => '2026-08-01', 'description' => 'Vente comptant', 'amount' => 150000, 'template_code' => 'vente_au_comptant_boutique'],
                ['date' => '2026-08-02', 'description' => 'Loyer aout', 'amount' => -45000, 'template_code' => 'location_loyer'],
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

it('genuinely supports a second, independently named caisse account (not hardcoded to one)', function () {
    // Real multi-caisse support: post into "Caisse secondaire" (5721), a
    // fully different account from "Caisse principale" (5711) used above,
    // proving the resolution is dynamic rather than a single hardcoded code.
    $response = $this->withToken($this->token)
        ->postJson('/api/v1/accounting/treasury-imports/commit', [
            'treasury_account_code' => '5721',
            'rows' => [
                ['date' => '2026-08-03', 'description' => 'Vente marchandises succursale', 'amount' => 60000, 'template_code' => 'vente_de_marchandises'],
            ],
        ])
        ->assertCreated();

    $entryId = $response->json('data.entries.0');
    $entry = JournalEntry::with('lines')->findOrFail($entryId);

    $caisseSecondaire = ChartOfAccount::where('code', '5721')->firstOrFail();
    $treasuryLine = $entry->lines->firstWhere('account_id', $caisseSecondaire->id);
    expect($treasuryLine)->not->toBeNull();
    expect((float) $treasuryLine->debit)->toBe(60000.0);

    $caisse = Journal::where('code', 'CAI')->firstOrFail();
    expect($entry->journal_id)->toBe($caisse->id);
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
            'treasury_account_code' => '5211',
            'bank_account_id' => $bank->id,
            'rows' => [
                ['date' => '2026-08-05', 'description' => 'Virement client', 'amount' => 200000, 'template_code' => 'vente_de_marchandises'],
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

    $bnqJournal = Journal::where('code', 'BNQ')->firstOrFail();
    $entry = JournalEntry::findOrFail($entryId);
    expect($entry->journal_id)->toBe($bnqJournal->id);
});

it('rejects a commit referencing an unknown operation template', function () {
    $this->withToken($this->token)
        ->postJson('/api/v1/accounting/treasury-imports/commit', [
            'treasury_account_code' => '5711',
            'rows' => [
                ['date' => '2026-08-01', 'description' => 'x', 'amount' => 1000, 'template_code' => 'nonexistent_template'],
            ],
        ])
        ->assertStatus(422);
});

// ─── Regression tests for the JournalEntryApiController fix ────────────────

it('creates a balanced journal entry via the generic API with real lines', function () {
    $bank = ChartOfAccount::where('code', '5211')->firstOrFail();
    $sales = ChartOfAccount::where('code', '701')->firstOrFail();

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
    expect($lines[0]['account']['code'])->toBe('5211');
    expect($lines[1]['account']['code'])->toBe('701');
});

it('rejects an unbalanced journal entry via the generic API', function () {
    $bank = ChartOfAccount::where('code', '5211')->firstOrFail();
    $sales = ChartOfAccount::where('code', '701')->firstOrFail();

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
    $bank = ChartOfAccount::where('code', '5211')->firstOrFail();
    $sales = ChartOfAccount::where('code', '701')->firstOrFail();

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
    expect((float) $byAccount['5211']['credit'])->toBe(1000.0);
    expect((float) $byAccount['701']['debit'])->toBe(1000.0);
});
