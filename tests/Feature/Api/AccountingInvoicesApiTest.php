<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Models\Invoice;
use Modules\Accounting\Models\Journal;

uses(RefreshDatabase::class);

// ── Auth guard ────────────────────────────────────────────────────────────────

test('unauthenticated requests are rejected', function () {
    $this->getJson('/api/v1/accounting/invoices')->assertUnauthorized();
    $this->postJson('/api/v1/accounting/invoices', [])->assertUnauthorized();
});

// ── Index ─────────────────────────────────────────────────────────────────────

test('index returns paginated invoices', function () {
    $user = actingAsUser('accountant');
    Invoice::factory()->count(3)->create(['created_by' => $user->id]);

    $this->getJson('/api/v1/accounting/invoices')
        ->assertOk()
        ->assertJsonStructure(['data', 'total', 'per_page', 'current_page']);
});

test('index returns all invoices', function () {
    $user = actingAsUser('accountant');
    Invoice::factory()->count(5)->create(['created_by' => $user->id]);

    $this->getJson('/api/v1/accounting/invoices')
        ->assertOk()
        ->assertJsonCount(5, 'data');
});

test('index filters by status', function () {
    $user = actingAsUser('accountant');
    Invoice::factory()->count(2)->create(['created_by' => $user->id, 'status' => 'draft']);
    Invoice::factory()->create(['created_by' => $user->id, 'status' => 'paid']);

    $this->getJson('/api/v1/accounting/invoices?status=draft')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

test('index filters by type', function () {
    $user = actingAsUser('accountant');
    Invoice::factory()->create(['created_by' => $user->id, 'type' => 'invoice']);
    Invoice::factory()->create(['created_by' => $user->id, 'type' => 'bill']);

    $this->getJson('/api/v1/accounting/invoices?type=bill')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

test('index searches by number', function () {
    $user = actingAsUser('accountant');
    Invoice::factory()->create(['created_by' => $user->id, 'number' => 'INV-UNIQUE-9999']);
    Invoice::factory()->count(2)->create(['created_by' => $user->id]);

    $this->getJson('/api/v1/accounting/invoices?search=INV-UNIQUE-9999')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

// ── Store ─────────────────────────────────────────────────────────────────────

test('can create an invoice', function () {
    actingAsUser('accountant');
    $journal = Journal::factory()->create();

    $this->postJson('/api/v1/accounting/invoices', [
            'type'         => 'invoice',
            'journal_id'   => $journal->id,
            'number'       => 'INV-TEST-001',
            'partner_name' => 'Acme Corp',
            'partner_type' => 'customer',
            'invoice_date' => '2025-01-15',
            'due_date'     => '2025-02-15',
            'lines'        => [
                [
                    'description' => 'Consulting services',
                    'quantity'    => 1,
                    'unit_price'  => 500.00,
                    'tax_rate'    => 20,
                ],
            ],
        ])
        ->assertCreated()
        ->assertJsonFragment(['number' => 'INV-TEST-001']);

    expect(Invoice::where('number', 'INV-TEST-001')->exists())->toBeTrue();
});

test('create calculates totals from lines', function () {
    actingAsUser('accountant');
    $journal = Journal::factory()->create();

    $response = $this->postJson('/api/v1/accounting/invoices', [
            'type'         => 'invoice',
            'journal_id'   => $journal->id,
            'number'       => 'INV-CALC-001',
            'invoice_date' => '2025-01-15',
            'lines'        => [
                ['description' => 'Item A', 'quantity' => 2, 'unit_price' => 100.00, 'tax_rate' => 10],
                ['description' => 'Item B', 'quantity' => 1, 'unit_price' => 200.00, 'tax_rate' => 0],
            ],
        ])
        ->assertCreated();

    expect((float) $response->json('data.subtotal'))->toBe(400.0)
        ->and((float) $response->json('data.tax_amount'))->toBe(20.0)
        ->and((float) $response->json('data.total'))->toBe(420.0);
});

test('create requires type journal_id number invoice_date and lines', function () {
    actingAsUser('accountant');

    $this->postJson('/api/v1/accounting/invoices', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['journal_id', 'invoice_date', 'lines']);
});

test('create rejects duplicate invoice number', function () {
    $user    = actingAsUser('accountant');
    $journal = Journal::factory()->create();
    Invoice::factory()->create(['number' => 'INV-DUP-001', 'created_by' => $user->id]);

    $this->postJson('/api/v1/accounting/invoices', [
            'type'         => 'invoice',
            'journal_id'   => $journal->id,
            'number'       => 'INV-DUP-001',
            'invoice_date' => '2025-01-15',
            'lines'        => [['description' => 'X', 'quantity' => 1, 'unit_price' => 10]],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['number']);
});

test('create requires at least one line', function () {
    actingAsUser('accountant');
    $journal = Journal::factory()->create();

    $this->postJson('/api/v1/accounting/invoices', [
            'type'         => 'invoice',
            'journal_id'   => $journal->id,
            'number'       => 'INV-NOLINE-001',
            'invoice_date' => '2025-01-15',
            'lines'        => [],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['lines']);
});

// ── Show ──────────────────────────────────────────────────────────────────────

test('can show an invoice', function () {
    $user    = actingAsUser('accountant');
    $invoice = Invoice::factory()->create(['created_by' => $user->id]);

    $this->getJson("/api/v1/accounting/invoices/{$invoice->id}")
        ->assertOk()
        ->assertJsonFragment(['id' => $invoice->id]);
});

// ── Mark Paid ─────────────────────────────────────────────────────────────────

test('can mark invoice as paid', function () {
    $user = actingAsUser('accountant');
    // Chantier 32.14 added a real lifecycle guard: a draft invoice must be
    // sent before it can be marked paid (markPaid() 422s otherwise) —
    // this fixture predates that fix and must start from 'sent', not 'draft'.
    $invoice = Invoice::factory()->create(['created_by' => $user->id, 'status' => 'sent']);

    $this->postJson("/api/v1/accounting/invoices/{$invoice->id}/mark-paid")
        ->assertOk()
        ->assertJsonFragment(['status' => 'paid']);

    expect($invoice->fresh()->status)->toBe('paid');
});

test('cannot mark a draft invoice as paid without sending it first', function () {
    $user    = actingAsUser('accountant');
    $invoice = Invoice::factory()->create(['created_by' => $user->id, 'status' => 'draft']);

    $this->postJson("/api/v1/accounting/invoices/{$invoice->id}/mark-paid")
        ->assertStatus(422);

    expect($invoice->fresh()->status)->toBe('draft');
});

// ── Destroy ───────────────────────────────────────────────────────────────────

test('can delete a draft invoice', function () {
    $user    = actingAsUser('accountant');
    $invoice = Invoice::factory()->create(['created_by' => $user->id, 'status' => 'draft']);

    $this->deleteJson("/api/v1/accounting/invoices/{$invoice->id}")
        ->assertNoContent();

    expect(Invoice::find($invoice->id))->toBeNull();
});

test('cannot delete a paid invoice', function () {
    $user    = actingAsUser('accountant');
    $invoice = Invoice::factory()->create(['created_by' => $user->id, 'status' => 'paid']);

    $this->deleteJson("/api/v1/accounting/invoices/{$invoice->id}")
        ->assertUnprocessable()
        ->assertJsonFragment(['message' => 'Only draft invoices can be deleted.']);

    expect(Invoice::find($invoice->id))->not->toBeNull();
});
