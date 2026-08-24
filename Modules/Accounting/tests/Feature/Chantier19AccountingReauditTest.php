<?php

declare(strict_types=1);

/**
 * Chantier 19 — second, empirical re-verification pass of every real user-facing
 * Accounting Vue page (excluding BalanceSheet/IncomeStatement/FinancialSimulation,
 * already verified in Chantier 18). Every test here hits the real HTTP route with a
 * real authenticated user and real seeded/created data — none construct a service
 * directly, since that style of test is exactly what let every one of these bugs
 * ship undetected through 3 earlier audits (Chantier 8.1, 8.1b, and Chantier 10).
 */

use Modules\Accounting\Models\AccBankFeed;
use Modules\Accounting\Models\AccBankFeedTransaction;
use Modules\Accounting\Models\AccOpenBankingConnection;
use Modules\Accounting\Models\BankAccount;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Models\Company;
use Modules\Accounting\Models\Invoice;
use Modules\Accounting\Models\InvoiceLine;
use Modules\Accounting\Models\Journal;
use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\OpenBankingConnection;

// ── Orphaned pages: 4 real Vue pages/controller methods with zero web route ────

test('journal-entries, currency, open-banking, and lettrage pages are now reachable', function () {
    $user = actingAsUser('accountant');

    $this->actingAs($user)->get('/accounting/journal-entries')->assertOk()
        ->assertInertia(fn ($page) => $page->component('Accounting/JournalEntries/Index'));

    $this->actingAs($user)->get('/accounting/currency')->assertOk()
        ->assertInertia(fn ($page) => $page->component('Accounting/Currency/Index'));

    $this->actingAs($user)->get('/accounting/open-banking')->assertOk()
        ->assertInertia(fn ($page) => $page->component('Accounting/OpenBanking/Index'));

    $this->actingAs($user)->get('/accounting/lettrage')->assertOk()
        ->assertInertia(fn ($page) => $page->component('Accounting/Lettrage'));
});

// ── OpenBanking/Index.vue: missing `feeds` route ────────────────────────────────

test('open banking feeds endpoint (called by selectConnection in the real page) is routed and returns real data', function () {
    $user = actingAsUser('accountant');
    $conn = AccOpenBankingConnection::factory()->create(['status' => 'active']);
    $feed = AccBankFeed::factory()->create(['connection_id' => $conn->id]);
    AccBankFeedTransaction::factory()->count(2)->create(['feed_id' => $feed->id]);

    $this->actingAs($user)
        ->getJson("/api/v1/accounting/open-banking/feeds/{$conn->id}")
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.id', $feed->id)
        ->assertJsonPath('0.transactions_count', 2);
});

// ── Invoices/Form.vue: total validation blocked every real create, and line items
//    were never persisted (nor exposed back by InvoiceResource) ────────────────

test('creating an invoice via the real customer+lines payload shape succeeds and persists real line items', function () {
    $user = actingAsUser('accountant');
    $customer = \App\Models\Customer::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/v1/accounting/invoices', [
        'type' => 'invoice',
        'customer_id' => $customer->id,
        'partner_name' => 'Test Client',
        'partner_type' => 'customer',
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'currency' => 'MGA',
        'status' => 'draft',
        'lines' => [
            ['description' => 'Service A', 'quantity' => 2, 'unit_price' => 50000, 'tax_rate' => 20],
        ],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.subtotal', 100000)
        ->assertJsonPath('data.tax_amount', 20000)
        ->assertJsonPath('data.total', 120000)
        ->assertJsonCount(1, 'data.line_items')
        ->assertJsonPath('data.line_items.0.description', 'Service A');

    $invoice = Invoice::find($response->json('data.id'));
    expect($invoice->lineItems()->count())->toBe(1);
});

test('updating a draft invoice with a new lines array replaces its line items and recomputes totals', function () {
    $user = actingAsUser('accountant');
    $customer = \App\Models\Customer::factory()->create();
    $invoice = Invoice::factory()->create(['status' => 'draft', 'customer_id' => $customer->id]);
    InvoiceLine::factory()->create(['invoice_id' => $invoice->id]);

    $response = $this->actingAs($user)->putJson("/api/v1/accounting/invoices/{$invoice->id}", [
        'lines' => [
            ['description' => 'Updated Line', 'quantity' => 3, 'unit_price' => 10000, 'tax_rate' => 20],
        ],
    ]);

    $response->assertOk()
        ->assertJsonPath('data.total', 36000);

    $invoice->refresh();
    expect($invoice->lineItems()->count())->toBe(1);
    expect($invoice->lineItems()->first()->description)->toBe('Updated Line');
});

test('invoice PDF export renders for a real invoice with real line items', function () {
    $user = actingAsUser('accountant');
    $customer = \App\Models\Customer::factory()->create();
    $storeResp = $this->actingAs($user)->postJson('/api/v1/accounting/invoices', [
        'type' => 'invoice',
        'customer_id' => $customer->id,
        'partner_name' => 'Test Client',
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'currency' => 'MGA',
        'status' => 'draft',
        'lines' => [['description' => 'Item', 'quantity' => 1, 'unit_price' => 5000, 'tax_rate' => 0]],
    ]);
    $invoiceId = $storeResp->json('data.id');

    // Chantier 19: was a guaranteed "Call to undefined relationship [lines]" fatal —
    // Invoice's real relation is lineItems(), which InvoicesIndex.vue's PDF icon
    // links directly to on every row.
    $this->actingAs($user)
        ->get("/api/v1/accounting/invoices/{$invoiceId}/pdf")
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

// ── Lettrage.vue: ledgerMatching()/ledgerMatch()/ledgerUnmatch() were literal stubs ─

test('lettrage lists real unmatched invoice lines, matches them, and unmatches them', function () {
    $user = actingAsUser('accountant');
    $account = ChartOfAccount::factory()->create(['code' => '411']);
    $inv1 = Invoice::factory()->create(['type' => 'invoice']);
    $inv2 = Invoice::factory()->create(['type' => 'bill']);
    $l1 = InvoiceLine::factory()->create(['invoice_id' => $inv1->id, 'account_id' => $account->id]);
    $l2 = InvoiceLine::factory()->create(['invoice_id' => $inv2->id, 'account_id' => $account->id]);

    $listResponse = $this->actingAs($user)->getJson('/api/v1/accounting/matching?account_code=411');
    // Chantier 19: was `return response()->json(['data' => [], 'total' => 0])`
    // unconditionally — a bare array of real rows is expected instead (the real
    // Lettrage.vue page does `lines.value = data` directly on the response body).
    $listResponse->assertOk();
    expect($listResponse->json())->toHaveCount(2);
    expect(collect($listResponse->json())->pluck('id'))->toContain($l1->id, $l2->id);

    $matchResponse = $this->actingAs($user)->postJson('/api/v1/accounting/matching/match', [
        'line_ids' => [$l1->id, $l2->id],
    ]);
    $matchResponse->assertOk()->assertJsonStructure(['match_ref']);
    $matchRef = $matchResponse->json('match_ref');

    // Chantier 19: previously a pure fake — never wrote match_ref anywhere.
    expect($l1->fresh()->match_ref)->toBe($matchRef);
    expect($l2->fresh()->match_ref)->toBe($matchRef);

    $this->actingAs($user)->postJson('/api/v1/accounting/matching/unmatch', ['match_ref' => $matchRef])
        ->assertOk();
    expect($l1->fresh()->match_ref)->toBeNull();
    expect($l2->fresh()->match_ref)->toBeNull();
});

// ── BankReconciliation/Reconcile.vue: auto-match filtered a never-populated column ─

test('bank reconciliation auto-match finds a real journal entry created via the real posting endpoint', function () {
    $user = actingAsUser('accountant');
    test()->seed(\Modules\Accounting\Database\Seeders\AccountingDatabaseSeeder::class);

    $storeResp = $this->actingAs($user)->postJson('/api/v1/accounting/journal-entries', [
        'date' => now()->toDateString(),
        'description' => 'Real posted sale',
        'lines' => [
            ['account_code' => '5211', 'debit' => 250000, 'credit' => 0],
            ['account_code' => '701', 'debit' => 0, 'credit' => 250000],
        ],
    ]);
    $storeResp->assertCreated();

    $bankAccount = BankAccount::factory()->create();
    $statement = \Modules\Accounting\Models\BankStatement::factory()->create(['bank_account_id' => $bankAccount->id]);
    $tx = \Modules\Accounting\Models\BankTransaction::factory()->create([
        'statement_id' => $statement->id,
        'amount' => 250000,
        'status' => 'unmatched',
        'transaction_date' => now(),
    ]);

    // Chantier 19: was guaranteed matched_count=0 for any real entry, since the
    // service filtered a raw DB::table('acc_journal_entries')->amount column the
    // real posting flow (JournalEntryApiController::store()) never populates.
    $response = $this->actingAs($user)
        ->postJson("/api/v1/accounting/bank-accounts/{$bankAccount->id}/transactions/auto-match");

    $response->assertOk()->assertJsonPath('matched_count', 1);
    expect($tx->fresh()->status)->toBe('matched');
});

// ── OpenBanking legacy sync flow (BankReconciliation/Index.vue's "Synchroniser") ──

test('legacy open banking sync completes without a fatal error and creates a real statement', function () {
    $user = actingAsUser('accountant');
    $account = BankAccount::factory()->create();
    $connection = OpenBankingConnection::factory()->create([
        'bank_account_id' => $account->id,
        'status' => 'active',
    ]);

    // Chantier 19: was a guaranteed "Call to undefined method
    // BankReconciliationService::importTransactions()" 500 on every real sync.
    $response = $this->actingAs($user)->postJson("/api/v1/accounting/open-banking/sync/{$connection->id}");

    $response->assertOk()->assertJsonPath('synced', 0);
    expect($account->fresh()->statements()->count())->toBe(1);
});

// ── Consolidation/*.vue + ConsolidationHierarchies/Index.vue: Company had no policy
//    at all, so every non-super-admin got an unconditional 403 despite being one of
//    the route group's own allowed roles ────────────────────────────────────────

test('an accountant (not super-admin) can create a consolidation company, record and eliminate an intercompany transaction, and generate a report', function () {
    $user = actingAsUser('accountant');

    $create = $this->actingAs($user)->postJson('/api/v1/accounting/consolidations', [
        'name' => 'Parent Co',
        'code' => 'PARENT-'.uniqid(),
        'company_type' => 'parent',
    ]);
    $create->assertCreated();
    $parentId = $create->json('id');

    $sub = Company::factory()->create(['parent_company_id' => $parentId, 'company_type' => 'subsidiary']);

    $this->actingAs($user)->postJson('/api/v1/accounting/intercompany-transactions', [
        'from_company_id' => $parentId,
        'to_company_id' => $sub->id,
        'amount' => 15000,
        'description' => 'Intercompany loan',
    ])->assertCreated();

    $this->actingAs($user)
        ->postJson('/api/v1/accounting/consolidations/eliminate', ['parent_company_id' => $parentId])
        ->assertOk()
        ->assertJsonPath('count', 1);

    $this->actingAs($user)->postJson("/api/v1/accounting/consolidations/{$parentId}/report", [
        'period_start' => now()->startOfMonth()->toDateString(),
        'period_end' => now()->endOfMonth()->toDateString(),
    ])->assertCreated();
});

test('a role outside the accounting route group still cannot create a consolidation company', function () {
    $user = actingAsUser('sales-rep');

    $this->actingAs($user)->postJson('/api/v1/accounting/consolidations', [
        'name' => 'X', 'code' => 'X1', 'company_type' => 'parent',
    ])->assertForbidden();
});
