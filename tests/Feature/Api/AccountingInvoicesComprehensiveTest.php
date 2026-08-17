<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Models\Invoice;
use Modules\Accounting\Models\Payment;
use Modules\CRM\Models\Account;

uses(RefreshDatabase::class);

// ── Auth & Authorization ──────────────────────────────────────────────────────

test('unauthenticated requests are rejected', function () {
    $this->getJson('/api/v1/accounting/invoices')->assertUnauthorized();
    $this->postJson('/api/v1/accounting/invoices', [])->assertUnauthorized();
});

test('accountant can create invoices', function () {
    $user = actingAsUser('accountant');
    $account = Account::factory()->create();
    $this->postJson('/api/v1/accounting/invoices', [
        'customer_id'  => $account->id,
        'invoice_date' => now()->toDateString(),
        'due_date'     => now()->addDays(30)->toDateString(),
        'total'        => 1000,
    ])->assertCreated();
});

test('employee cannot delete invoice', function () {
    $user = actingAsUser('employee');
    $invoice = Invoice::factory()->create();
    $this->deleteJson("/api/v1/accounting/invoices/{$invoice->id}")
        ->assertForbidden();
});

// ── Index ─────────────────────────────────────────────────────────────────────

test('index returns paginated invoices', function () {
    $user = actingAsUser('accountant');
    Invoice::factory()->count(25)->create();
    $response = $this
        ->getJson('/api/v1/accounting/invoices')
        ->assertOk()
        ->assertJsonStructure(['data', 'total', 'per_page', 'current_page']);
    expect($response->json('total'))->toBe(25);
});

test('index filters by status', function () {
    $user = actingAsUser('accountant');
    Invoice::factory()->count(5)->create(['status' => 'draft']);
    Invoice::factory()->count(3)->create(['status' => 'sent']);
    Invoice::factory()->count(2)->create(['status' => 'paid']);

    $response = $this
        ->getJson('/api/v1/accounting/invoices?status=sent')
        ->assertOk();
    expect($response->json('total'))->toBe(3);
});

test('index filters by customer', function () {
    $user = actingAsUser('accountant');
    $account1 = Account::factory()->create();
    $account2 = Account::factory()->create();

    Invoice::factory()->count(4)->create(['customer_id' => $account1->id]);
    Invoice::factory()->count(2)->create(['customer_id' => $account2->id]);

    $response = $this
        ->getJson("/api/v1/accounting/invoices?customer_id={$account1->id}")
        ->assertOk();
    expect($response->json('total'))->toBe(4);
});

test('index filters by date range', function () {
    $user = actingAsUser('accountant');
    Invoice::factory()->create(['invoice_date' => now()->subDays(10)->toDateString()]);
    Invoice::factory()->create(['invoice_date' => now()->toDateString()]);
    Invoice::factory()->create(['invoice_date' => now()->addDays(10)->toDateString()]);

    $response = $this
        ->getJson('/api/v1/accounting/invoices?invoice_date_from=' . now()->subDays(5)->toDateString())
        ->assertOk();
    expect($response->json('total'))->toBe(2);
});

test('index searches by invoice number', function () {
    $user = actingAsUser('accountant');
    Invoice::factory()->create(['number' => 'INV-2026-001']);
    Invoice::factory()->create(['invoice_number' => 'INV-2026-002']);

    $response = $this
        ->getJson('/api/v1/accounting/invoices?search=INV-2026-001')
        ->assertOk();
    expect($response->json('total'))->toBe(1);
});

test('index sorts by total amount', function () {
    $user = actingAsUser('accountant');
    Invoice::factory()->create(['invoice_number' => 'Small', 'total' => 1000]);
    Invoice::factory()->create(['invoice_number' => 'Large', 'total' => 50000]);

    $response = $this
        ->getJson('/api/v1/accounting/invoices?sort=-total')
        ->assertOk();
    expect($response->json('data')[0]['invoice_number'])->toBe('Large');
});

// ── Store ─────────────────────────────────────────────────────────────────────

test('can create invoice with required fields', function () {
    $user = actingAsUser('accountant');
    $account = Account::factory()->create();
    $response = $this
        ->postJson('/api/v1/accounting/invoices', [
            'customer_id'  => $account->id,
            'invoice_date' => now()->toDateString(),
            'due_date'     => now()->addDays(30)->toDateString(),
            'total'        => 1000,
        ])
        ->assertCreated();
    expect(Invoice::where('customer_id', $account->id)->exists())->toBeTrue();
});

test('can create invoice with line items', function () {
    $user = actingAsUser('accountant');
    $account = Account::factory()->create();
    $response = $this
        ->postJson('/api/v1/accounting/invoices', [
            'customer_id'  => $account->id,
            'invoice_date' => now()->toDateString(),
            'due_date'     => now()->addDays(30)->toDateString(),
            'total'        => 1000,
            'line_items'   => [
                ['description' => 'Service A', 'qty' => 1, 'unit_price' => 500],
                ['description' => 'Service B', 'qty' => 2, 'unit_price' => 250],
            ],
        ])
        ->assertCreated();
});

test('create requires customer_id, invoice_date, due_date, total', function () {
    $user = actingAsUser('accountant');
    $this->postJson('/api/v1/accounting/invoices', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['customer_id', 'invoice_date', 'due_date', 'total']);
});

test('create validates date format', function () {
    $user = actingAsUser('accountant');
    $account = Account::factory()->create();
    $this->postJson('/api/v1/accounting/invoices', [
        'customer_id'  => $account->id,
        'invoice_date' => 'invalid',
        'due_date'     => now()->toDateString(),
        'total'        => 1000,
    ])->assertUnprocessable()->assertJsonValidationErrors(['invoice_date']);
});

test('create validates total is numeric', function () {
    $user = actingAsUser('accountant');
    $account = Account::factory()->create();
    $this->postJson('/api/v1/accounting/invoices', [
        'customer_id'  => $account->id,
        'invoice_date' => now()->toDateString(),
        'due_date'     => now()->toDateString(),
        'total'        => 'not-a-number',
    ])->assertUnprocessable()->assertJsonValidationErrors(['total']);
});

test('created invoice defaults to draft status', function () {
    $user = actingAsUser('accountant');
    $account = Account::factory()->create();
    $response = $this
        ->postJson('/api/v1/accounting/invoices', [
            'customer_id'  => $account->id,
            'invoice_date' => now()->toDateString(),
            'due_date'     => now()->addDays(30)->toDateString(),
            'total'        => 1000,
        ])
        ->assertCreated();
    expect($response->json('status'))->toBe('draft');
});

// ── Show ──────────────────────────────────────────────────────────────────────

test('can show invoice with payments', function () {
    $user = actingAsUser('accountant');
    $invoice = Invoice::factory()->has(Payment::factory()->count(2))->create();
    $response = $this
        ->getJson("/api/v1/accounting/invoices/{$invoice->id}")
        ->assertOk()
        ->assertJsonFragment(['id' => $invoice->id]);
});

test('show returns 404 for missing invoice', function () {
    $user = actingAsUser('accountant');
    $this->getJson('/api/v1/accounting/invoices/99999')->assertNotFound();
});

// ── Update ────────────────────────────────────────────────────────────────────

test('can update draft invoice', function () {
    $user = actingAsUser('accountant');
    $invoice = Invoice::factory()->create(['status' => 'draft', 'total' => 1000]);
    $this
        ->putJson("/api/v1/accounting/invoices/{$invoice->id}", ['total' => 1500])
        ->assertOk();
    expect($invoice->fresh()->total)->toBe(1500);
});

test('cannot update sent invoice', function () {
    $user = actingAsUser('accountant');
    $invoice = Invoice::factory()->create(['status' => 'sent']);
    $this->putJson("/api/v1/accounting/invoices/{$invoice->id}", ['total' => 2000])
        ->assertForbidden();
});

test('can change invoice status to sent', function () {
    $user = actingAsUser('accountant');
    $invoice = Invoice::factory()->create(['status' => 'draft']);
    $this
        ->patchJson("/api/v1/accounting/invoices/{$invoice->id}/status", ['status' => 'sent'])
        ->assertOk();
    expect($invoice->fresh()->status)->toBe('sent');
});

// ── Payments ──────────────────────────────────────────────────────────────────

test('can record payment on invoice', function () {
    $user = actingAsUser('accountant');
    $invoice = Invoice::factory()->create(['total' => 1000, 'paid_amount' => 0]);
    $response = $this
        ->postJson("/api/v1/accounting/invoices/{$invoice->id}/payments", [
            'amount'        => 500,
            'payment_date'  => now()->toDateString(),
            'method'        => 'bank_transfer',
            'reference'     => 'TRF-001',
        ])
        ->assertCreated();
    expect($invoice->fresh()->paid_amount)->toBe(500);
    $this->assertDatabaseHas('acc_invoice_payments', [
        'invoice_id' => $invoice->id,
        'amount' => 500,
        'payment_method' => 'bank_transfer',
        'reference' => 'TRF-001',
    ]);
});

test('can record partial payments', function () {
    $user = actingAsUser('accountant');
    $invoice = Invoice::factory()->create(['total' => 1000]);
    $this->postJson("/api/v1/accounting/invoices/{$invoice->id}/payments", [
        'amount'       => 300,
        'payment_date' => now()->toDateString(),
        'method'       => 'check',
    ])->assertCreated();
    $this->postJson("/api/v1/accounting/invoices/{$invoice->id}/payments", [
        'amount'       => 700,
        'payment_date' => now()->toDateString(),
        'method'       => 'bank_transfer',
    ])->assertCreated();
    expect($invoice->fresh()->paid_amount)->toBe(1000);
});

test('validates payment amount does not exceed total', function () {
    $user = actingAsUser('accountant');
    $invoice = Invoice::factory()->create(['total' => 1000]);
    $this->postJson("/api/v1/accounting/invoices/{$invoice->id}/payments", [
        'amount'       => 1500,
        'payment_date' => now()->toDateString(),
        'method'       => 'bank_transfer',
    ])->assertUnprocessable();
});

// ── Destroy ───────────────────────────────────────────────────────────────────

test('can delete draft invoice', function () {
    $user = actingAsUser('manager');
    $invoice = Invoice::factory()->create(['status' => 'draft']);
    $this->deleteJson("/api/v1/accounting/invoices/{$invoice->id}")->assertNoContent();
    expect(Invoice::find($invoice->id))->toBeNull();
});

test('cannot delete sent invoice', function () {
    $user = actingAsUser('manager');
    $invoice = Invoice::factory()->create(['status' => 'sent']);
    $this->deleteJson("/api/v1/accounting/invoices/{$invoice->id}")->assertForbidden();
});

// ── Reporting ─────────────────────────────────────────────────────────────────

test('can get invoice totals by status', function () {
    $user = actingAsUser('accountant');
    Invoice::factory()->create(['status' => 'draft', 'total' => 1000]);
    Invoice::factory()->create(['status' => 'sent', 'total' => 2000]);
    Invoice::factory()->create(['status' => 'paid', 'total' => 3000]);

    $response = $this
        ->getJson('/api/v1/accounting/invoices/summary')
        ->assertOk();
    expect($response->json())->toHaveKey('by_status');
});

test('can get aged receivables report', function () {
    $user = actingAsUser('accountant');
    Invoice::factory()->create([
        'status'     => 'sent',
        'due_date'   => now()->subDays(30)->toDateString(),
        'paid_amount' => 0,
        'total'      => 1000,
    ]);
    Invoice::factory()->create([
        'status'     => 'sent',
        'due_date'   => now()->subDays(5)->toDateString(),
        'paid_amount' => 0,
        'total'      => 2000,
    ]);

    $response = $this
        ->getJson('/api/v1/accounting/invoices/aged-receivables')
        ->assertOk();
    expect($response->json())->toHaveKeys(['over_90_days', 'over_60_days', 'over_30_days', 'current']);
});
